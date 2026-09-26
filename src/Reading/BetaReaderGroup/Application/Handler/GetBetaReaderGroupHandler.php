<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Handler;

use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\BetaReaderGroupDetail;
use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\GroupMemberCard;
use LectoresBeta\Reading\BetaReaderGroup\Application\Query\GetBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Application\Service\OwnGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroupMember;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupMemberRepository;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;

/**
 * Un grupo abierto, con quién hay dentro (`FEAT-RDG-007`).
 *
 * Se lee con **`ProfileCards` y no con `VisibleProfiles`**, por el mismo
 * motivo que la lista de bloqueados (`FEAT-COM-034`): quien pregunta ya sabe
 * quiénes son, porque los metió él. Filtrando por privacidad, un miembro que
 * cierre su perfil desaparecería del grupo y el autor no podría ni quitarlo —
 * una lista de la que no se puede borrar a nadie.
 *
 * Una cuenta eliminada sí desaparece de la tarjeta: no queda nada que
 * enseñar. La fila se mantiene con lo que se sabe, el identificador, para que
 * el autor pueda quitarla.
 */
final readonly class GetBetaReaderGroupHandler
{
    public function __construct(
        private OwnGroup $ownGroup,
        private BetaReaderGroupMemberRepository $members,
        private ProfileCards $cards,
    ) {
    }

    public function __invoke(GetBetaReaderGroup $query): BetaReaderGroupDetail
    {
        $group = $this->ownGroup->of($query->groupId, $query->authorId);
        $members = $this->members->of($group->id());

        $cards = $this->cards->of(array_map(
            static fn (BetaReaderGroupMember $member): string => $member->readerId()->value(),
            $members,
        ));

        return new BetaReaderGroupDetail(
            $group->id()->value(),
            $group->name()->value(),
            $group->createdAt(),
            array_map(
                static function (BetaReaderGroupMember $member) use ($cards): GroupMemberCard {
                    $card = $cards[$member->readerId()->value()] ?? null;

                    return new GroupMemberCard(
                        $member->readerId()->value(),
                        $card?->username,
                        $card?->name,
                        $card?->avatarUrl,
                        $member->addedAt(),
                    );
                },
                $members,
            ),
        );
    }
}
