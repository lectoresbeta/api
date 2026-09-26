<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Recommendation\Application\Event\LiteraryPreferencesUpdated;
use LectoresBeta\Community\Recommendation\Domain\Entity\MemberGenre;
use LectoresBeta\Community\Recommendation\Domain\Repository\MemberGenreRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * La copia local de qué le interesa a cada persona (`FEAT-COM-016` `RN-1`).
 *
 * **Se sustituye entera**, que es lo que significa el hecho: «estos son sus
 * géneros», no «ha añadido uno». Reconciliar diferencias daría el mismo
 * resultado con más código y una forma más de equivocarse.
 *
 * Sin control de duplicados y sin comprobar que la cuenta exista: volver a
 * procesar el mismo hecho escribe lo mismo, y desconfiar de quien es dueño de
 * esa decisión sería rehacer su trabajo con información más vieja.
 */
final readonly class ProjectMemberGenres
{
    public function __construct(
        private MemberGenreRepository $genres,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(LiteraryPreferencesUpdated $event): void
    {
        try {
            $member = MemberId::fromString($event->userId);
        } catch (InvalidValue) {
            // Un hecho con un identificador ilegible no se reintenta: se
            // descarta. Reintentarlo lo dejaría dando vueltas para siempre.
            return;
        }

        $this->session->execute(function () use ($member, $event): void {
            $this->genres->replaceAllOf($member, array_map(
                static fn (string $code): MemberGenre => new MemberGenre($member, $code),
                $event->genres,
            ));
        });
    }
}
