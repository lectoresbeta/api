<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Service;

use LectoresBeta\Community\Messaging\Domain\Entity\Conversation;
use LectoresBeta\Community\Messaging\Domain\Exception\ConversationNotFound;
use LectoresBeta\Community\Messaging\Domain\Repository\ConversationRepository;
use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * La conversación de quien pregunta, o ninguna (`FEAT-COM-012` `RN-1`).
 *
 * **No existir y no ser tuya responden igual.** Un `403` a una conversación
 * ajena confirmaría que existe, y con ella que esas dos personas hablan.
 * Quién habla con quién no es información que se le deba a nadie.
 *
 * Está en un sitio porque las tres operaciones de lectura la necesitan, y
 * escrita tres veces sería la tercera la que se olvidara.
 */
final readonly class MyConversation
{
    public function __construct(private ConversationRepository $conversations)
    {
    }

    public function of(string $memberId, string $conversationId): Conversation
    {
        try {
            $conversation = $this->conversations->ofId(ConversationId::fromString($conversationId));
            $member = MemberId::fromString($memberId);
        } catch (InvalidValue) {
            throw ConversationNotFound::create();
        }

        if (null === $conversation || !$conversation->includes($member)) {
            throw ConversationNotFound::create();
        }

        return $conversation;
    }
}
