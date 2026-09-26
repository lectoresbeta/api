<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Handler;

use LectoresBeta\Reading\AccessInvitation\Application\Command\InviteBetaReader;
use LectoresBeta\Reading\AccessInvitation\Application\Handler\InviteBetaReaderHandler;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationNotFound;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationRefused;
use LectoresBeta\Reading\BetaReaderGroup\Application\Command\InviteBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\GroupInvitationResult;
use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\SkippedMember;
use LectoresBeta\Reading\BetaReaderGroup\Application\Service\OwnGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupMemberRepository;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Invitar a todo el grupo a una obra (`FEAT-RDG-007` `RN-12`, cierra `R-15`).
 *
 * **El grupo es el atajo, no la puerta.** Esto no concede acceso a nadie: lo
 * que hace es recorrer los miembros y cursar **una invitación normal por cada
 * uno**, exactamente la de `FEAT-RDG-004`, con sus mismas comprobaciones y su
 * mismo evento. Cada persona recibe su aviso y decide por su cuenta.
 *
 * Por eso reutiliza `InviteBetaReaderHandler` en lugar de escribir las reglas
 * otra vez: dos sitios donde nace una invitación son dos sitios donde una
 * regla se puede quedar sin aplicar, y la que más importa —que el
 * destinatario tenga edad para una obra `ADULTS_ONLY`— es justo la que nadie
 * echaría de menos hasta que fallara.
 *
 * **No es atómica**, a propósito. Unos se invitan y otros no, y la respuesta
 * dice cuáles y por qué: devolver `422` porque uno de doce no se puede
 * invitar dejaría al autor sin los once que sí.
 */
final readonly class InviteBetaReaderGroupHandler
{
    public function __construct(
        private OwnGroup $ownGroup,
        private BetaReaderGroupMemberRepository $members,
        private WorkAccessBriefs $works,
        private InviteBetaReaderHandler $invite,
    ) {
    }

    public function __invoke(InviteBetaReaderGroup $command): GroupInvitationResult
    {
        $group = $this->ownGroup->of($command->groupId ?? '', $command->authorId);

        // La obra se mira **antes** del bucle y no solo dentro de cada
        // invitación. Con el grupo vacío no habría ninguna invitación, y
        // entonces invitar a la obra de otro respondería `200` con una lista
        // vacía en lugar de `404`.
        $work = $this->works->ofWork($command->workId);

        if (null === $work || $work->authorId !== $command->authorId) {
            throw InvitationNotFound::work();
        }

        $invited = [];
        $skipped = [];

        foreach ($this->members->of($group->id()) as $member) {
            $readerId = $member->readerId()->value();

            try {
                ($this->invite)(new InviteBetaReader(
                    $command->workId,
                    $command->authorId,
                    $readerId,
                    $command->message,
                ));

                $invited[] = $readerId;
            } catch (InvitationRefused $refusal) {
                // El motivo que viaja es **el mismo código** que habría
                // devuelto la invitación individual. Inventar un vocabulario
                // distinto para el caso en bloque obligaría al cliente a
                // conocer dos.
                $skipped[] = new SkippedMember($readerId, $refusal->errorCode());
            }
        }

        return new GroupInvitationResult($group->id()->value(), $invited, $skipped);
    }
}
