<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Handler;

use LectoresBeta\Community\Curation\Application\DTO\MutedPage;
use LectoresBeta\Community\Curation\Application\Query\ListMutedUsers;
use LectoresBeta\Community\Curation\Domain\Entity\MutedMember;
use LectoresBeta\Community\Curation\Domain\Repository\MutedMemberRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;

/**
 * A quién tengo silenciado (`FEAT-COM-033` `RN-8`).
 *
 * Usa `ProfileCards` y **no** `VisibleProfiles`, la misma decisión que la
 * lista de bloqueados y por la misma razón: filtrando por privacidad, alguien
 * que cierre su perfil desaparecería de aquí y quedaría silenciado para
 * siempre. Quien pregunta ya sabe quiénes son — los silenció él.
 *
 * Solo por uno mismo. A quién silencia otra persona no es asunto de nadie, y
 * ni siquiera hay endpoint que lo pregunte.
 */
final readonly class ListMutedUsersHandler
{
    public function __construct(
        private MutedMemberRepository $muted,
        private ProfileCards $profiles,
    ) {
    }

    public function __invoke(ListMutedUsers $query): MutedPage
    {
        try {
            $member = MemberId::fromString($query->memberId);
        } catch (InvalidValue) {
            return new MutedPage([], null);
        }

        $limit = PageSize::of($query->limit);
        $after = null === $query->cursor || '' === $query->cursor
            ? null
            : Cursor::decode($query->cursor) ?? throw InvalidCursor::create();

        $found = $this->muted->pageOf($member, $after, $limit);
        $rows = \array_slice($found, 0, $limit);
        $last = end($rows);

        $cards = $this->profiles->of(array_values(array_map(
            static fn (MutedMember $muted): string => $muted->mutedId()->value(),
            $rows,
        )));

        $people = [];

        foreach ($rows as $muted) {
            $card = $cards[$muted->mutedId()->value()] ?? null;

            // Una cuenta eliminada no tiene tarjeta. Sigue silenciada —la
            // fila está ahí— pero no hay nada que enseñar de ella.
            if (null !== $card) {
                $people[] = ['profile' => $card, 'mutedAt' => $muted->mutedAt()];
            }
        }

        return new MutedPage(
            $people,
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->mutedAt(), $last->mutedId()->value())->encode()
                : null,
        );
    }
}
