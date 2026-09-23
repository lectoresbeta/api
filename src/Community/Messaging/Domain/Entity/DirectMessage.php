<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\Entity;

use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Messaging\Domain\ValueObject\DirectMessageId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * One message in a conversation.
 *
 * The body stays here and never travels in a notification email (`RN-4` of
 * `Notification`): the email carries a notice and a link, nothing else.
 */
class DirectMessage
{
    private string $id;

    private string $conversationId;

    private string $senderId;

    private string $body;

    private \DateTimeImmutable $sentAt;

    private ?\DateTimeImmutable $readAt = null;

    public function __construct(
        DirectMessageId $id,
        ConversationId $conversationId,
        MemberId $senderId,
        string $body,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->conversationId = $conversationId->value();
        $this->senderId = $senderId->value();
        $this->body = $body;
        $this->sentAt = $now;
    }

    public function id(): DirectMessageId
    {
        return DirectMessageId::fromString($this->id);
    }

    public function conversationId(): ConversationId
    {
        return ConversationId::fromString($this->conversationId);
    }

    public function senderId(): MemberId
    {
        return MemberId::fromString($this->senderId);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function markRead(\DateTimeImmutable $now): void
    {
        $this->readAt ??= $now;
    }
}
