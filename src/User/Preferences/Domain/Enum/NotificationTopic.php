<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Enum;

/**
 * What a person can choose to be told about (`FEAT-USR-039`).
 *
 * It is a *topic*, not the delivery catalogue of `Notification`: this context
 * owns the preference, the other owns the notification. They are duplicated
 * on purpose — a shared enum would couple the two contexts (`AGENTS.md`).
 *
 * `CORRECTION_RECEIVED` and `CHAPTER_COMMENT` are separate (`S-11`). Folding
 * them together would mean that muting social chatter also mutes the
 * corrections the author has paid for.
 *
 * Operational messages — activation, password reset, security warnings, legal
 * notices — are not here at all (`RN-3`). They are not notifications, and the
 * master switch does not reach them either.
 */
enum NotificationTopic: string
{
    case CORRECTION_RECEIVED = 'CORRECTION_RECEIVED';
    case CHAPTER_COMMENT = 'CHAPTER_COMMENT';
    case CORRECTION_REPLIED = 'CORRECTION_REPLIED';
    case CORRECTION_RATED = 'CORRECTION_RATED';
    case ACCESS_REQUESTED = 'ACCESS_REQUESTED';
    case ACCESS_RESOLVED = 'ACCESS_RESOLVED';
    case BETA_READER_INVITATION = 'BETA_READER_INVITATION';
    case WRITING_BUDDY_PROPOSED = 'WRITING_BUDDY_PROPOSED';
    case DIRECT_MESSAGE = 'DIRECT_MESSAGE';
    case MENTION = 'MENTION';
    case POST_REPLY = 'POST_REPLY';
    case SUBSCRIBED_AUTHOR_ACTIVITY = 'SUBSCRIBED_AUTHOR_ACTIVITY';
    case CREDIT_MOVEMENT = 'CREDIT_MOVEMENT';
    case PLATFORM_UPDATES = 'PLATFORM_UPDATES';

    /**
     * Everything is on by default except platform updates (`RN-4`). A missing
     * row means this value, which is what lets new topics ship without a
     * migration.
     */
    public function enabledByDefault(): bool
    {
        return self::PLATFORM_UPDATES !== $this;
    }
}
