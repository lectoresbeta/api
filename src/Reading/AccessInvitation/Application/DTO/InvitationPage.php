<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\DTO;

/**
 * A page of invitations and where the next one starts.
 *
 * By cursor, like its twin on the request side: a tray, not a catalogue.
 */
final readonly class InvitationPage
{
    /**
     * @param list<InvitationView> $invitations
     */
    public function __construct(
        public array $invitations,
        public ?string $nextCursor,
    ) {
    }
}
