<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Http;

use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\BetaReaderGroupDetail;
use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\BetaReaderGroupSummary;
use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\GroupInvitationResult;
use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\GroupMemberCard;
use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\SkippedMember;

/**
 * La forma de un grupo de lectores beta en HTTP (`FEAT-RDG-007`).
 */
final readonly class BetaReaderGroupBody
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(BetaReaderGroupSummary $group): array
    {
        return [
            'groupId' => $group->groupId,
            'name' => $group->name,
            'memberCount' => $group->memberCount,
            'createdAt' => $group->createdAt->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(BetaReaderGroupDetail $group): array
    {
        return [
            'groupId' => $group->groupId,
            'name' => $group->name,
            'memberCount' => \count($group->members),
            'createdAt' => $group->createdAt->format(\DATE_ATOM),
            'members' => array_map(
                static fn (GroupMemberCard $member): array => [
                    'userId' => $member->userId,
                    'username' => $member->username,
                    'name' => $member->name,
                    'avatarUrl' => $member->avatarUrl,
                    'addedAt' => $member->addedAt->format(\DATE_ATOM),
                ],
                $group->members,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invitationResult(GroupInvitationResult $result): array
    {
        return [
            'groupId' => $result->groupId,
            'invited' => $result->invited,
            'skipped' => array_map(
                static fn (SkippedMember $skipped): array => [
                    'userId' => $skipped->userId,
                    'reason' => $skipped->reason,
                ],
                $result->skipped,
            ),
        ];
    }
}
