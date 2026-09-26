<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Application\DTO\BlockedPage;
use LectoresBeta\Community\Relationship\Application\Query\ListBlockedUsers;
use LectoresBeta\Community\Relationship\Domain\Entity\UserBlock;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;

/**
 * A quién tengo bloqueado (`FEAT-COM-034`).
 *
 * Usa `ProfileCards` y **no** `VisibleProfiles`, que es la decisión que
 * importa: si se filtrara por privacidad, bloquear a alguien que después
 * cierra su perfil lo haría desaparecer de aquí y el bloqueo quedaría sin
 * deshacer para siempre. Quien pregunta ya sabe quiénes son — los bloqueó él.
 *
 * Solo por uno mismo. Quién ha bloqueado otra persona no es asunto de nadie,
 * y ni siquiera hay endpoint que lo pregunte.
 */
final readonly class ListBlockedUsersHandler
{
    public function __construct(
        private UserBlockRepository $blocks,
        private ProfileCards $profiles,
    ) {
    }

    public function __invoke(ListBlockedUsers $query): BlockedPage
    {
        try {
            $blockerId = MemberId::fromString($query->userId);
        } catch (InvalidValue) {
            return new BlockedPage([], null);
        }

        $limit = PageSize::of($query->limit);
        $after = null === $query->cursor || '' === $query->cursor
            ? null
            : Cursor::decode($query->cursor) ?? throw InvalidCursor::create();

        $found = $this->blocks->blockedBy($blockerId, $after, $limit);
        $rows = \array_slice($found, 0, $limit);
        $last = end($rows);

        $cards = $this->profiles->of(array_values(array_map(
            static fn (UserBlock $block): string => $block->blockedId()->value(),
            $rows,
        )));

        $people = [];

        foreach ($rows as $block) {
            $card = $cards[$block->blockedId()->value()] ?? null;

            // Una cuenta eliminada no tiene tarjeta. Sigue bloqueada —la fila
            // está ahí— pero no hay nada que enseñar de ella.
            if (null !== $card) {
                $people[] = ['profile' => $card, 'blockedAt' => $block->createdAt()];
            }
        }

        return new BlockedPage(
            $people,
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->createdAt(), $last->id()->value())->encode()
                : null,
        );
    }
}
