<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Referral\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Referral\Domain\Entity\Referral;

interface ReferralRepository
{
    public function ofInvitee(UserId $inviteeId): ?Referral;

    /**
     * Cuántas invitaciones de esta persona ya se han pagado.
     *
     * Existe por el tope de `decision:0006`, regla 6: diez por usuario, para
     * que nadie construya su saldo reclutando en lugar de corrigiendo.
     */
    public function rewardedCountOf(UserId $inviterId): int;

    public function save(Referral $referral): void;
}
