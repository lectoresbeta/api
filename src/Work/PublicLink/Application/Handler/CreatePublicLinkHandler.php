<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\PublicLink\Application\Command\CreatePublicLink;
use LectoresBeta\Work\PublicLink\Application\DTO\CreatedPublicLink;
use LectoresBeta\Work\PublicLink\Application\Service\DescribePublicLink;
use LectoresBeta\Work\PublicLink\Domain\Entity\PublicLink;
use LectoresBeta\Work\PublicLink\Domain\Exception\PublicLinkRefused;
use LectoresBeta\Work\PublicLink\Domain\Repository\PublicLinkRepository;
use LectoresBeta\Work\PublicLink\Domain\Service\PublicLinkToken;
use LectoresBeta\Work\PublicLink\Domain\ValueObject\PublicLinkId;

/**
 * Generar un enlace público (`FEAT-WRK-010`).
 *
 * **Devuelve el token una sola vez y no lo guarda.** Lo que queda en la base
 * de datos es su `sha256`, que sirve para buscar y no para reconstruirlo. El
 * autor que pierde la URL crea otro enlace: cuesta un clic, y una credencial
 * en claro en una copia de seguridad no se deshace.
 *
 * No se comprueba el estado de la obra (`RN-14`). El enlace funciona sobre un
 * borrador, que es justamente el caso: se reparte para conseguir feedback
 * **antes** de publicar.
 */
final readonly class CreatePublicLinkHandler
{
    public function __construct(
        private WorkRepository $works,
        private PublicLinkRepository $links,
        private DescribePublicLink $describe,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreatePublicLink $command): CreatedPublicLink
    {
        $work = $this->owned($command->workId, $command->authorId);
        $now = $this->clock->now();

        $cap = $command->maxCorrections ?? PublicLink::DEFAULT_MAX_CORRECTIONS;

        if ($cap < 1 || $cap > PublicLink::MAX_CORRECTIONS_CEILING) {
            throw PublicLinkRefused::capOutOfRange();
        }

        $expiresAt = self::moment($command->expiresAt);

        if (null !== $expiresAt && $expiresAt <= $now) {
            throw PublicLinkRefused::expiryInThePast();
        }

        $token = PublicLinkToken::generate();

        $link = new PublicLink(
            PublicLinkId::generate(),
            $work->id(),
            PublicLinkToken::hash($token),
            $now,
            $cap,
            $expiresAt,
            self::label($command->label),
        );

        $this->session->execute(function () use ($link): void {
            $this->links->save($link);
        });

        return new CreatedPublicLink($this->describe->one($link, $now), $token);
    }

    private static function label(?string $label): ?string
    {
        $label = null === $label ? '' : trim($label);

        return '' === $label ? null : mb_substr($label, 0, 80);
    }

    private static function moment(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw PublicLinkRefused::expiryInThePast();
        }
    }

    private function owned(string $workId, string $authorId): Work
    {
        try {
            $work = $this->works->ofId(WorkId::fromString($workId));
            $author = AuthorId::fromString($authorId);
        } catch (InvalidValue) {
            throw PublicLinkRefused::workNotYours();
        }

        if (null === $work || !$work->authorId()->equals($author)) {
            throw PublicLinkRefused::workNotYours();
        }

        return $work;
    }
}
