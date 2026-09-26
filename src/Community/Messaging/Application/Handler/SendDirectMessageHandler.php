<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Handler;

use LectoresBeta\Community\Messaging\Application\Command\SendDirectMessage;
use LectoresBeta\Community\Messaging\Application\DTO\SentMessage;
use LectoresBeta\Community\Messaging\Domain\Entity\Conversation;
use LectoresBeta\Community\Messaging\Domain\Entity\DirectMessage;
use LectoresBeta\Community\Messaging\Domain\Event\DirectMessageSent;
use LectoresBeta\Community\Messaging\Domain\Exception\ConversationNotFound;
use LectoresBeta\Community\Messaging\Domain\Exception\MessageRefused;
use LectoresBeta\Community\Messaging\Domain\Repository\ConversationRepository;
use LectoresBeta\Community\Messaging\Domain\Repository\DirectMessageRepository;
use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Messaging\Domain\ValueObject\DirectMessageId;
use LectoresBeta\Community\Messaging\Domain\ValueObject\MessageBody;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;
use LectoresBeta\User\Privacy\Application\Contract\MessageAudience;

/**
 * Escribirle a alguien (`FEAT-COM-011`).
 *
 * **Dos puertas, y son dos porque significan cosas distintas.**
 *
 * El ajuste de privacidad es una preferencia —«prefiero que no me escriba
 * cualquiera»— y gobierna **abrir** una conversación. El bloqueo es una regla
 * de acceso contra una persona concreta, y corta siempre, también lo ya
 * abierto.
 *
 * De ahí `RN-4`, que es lo que más fácil se lee al revés: **endurecer el
 * ajuste no cierra los hilos abiertos**. Si lo hiciera, «prefiero que no me
 * escriban» se convertiría en «desaparezco de conversaciones que estaba
 * teniendo», y quien está esperando una respuesta a medias no vuelve a
 * recibirla.
 *
 * El ajuste lo responde `User` con un booleano por su contrato publicado:
 * este contexto no ve nunca el valor, y así la regla de qué significa
 * «seguidores» vive en un solo sitio.
 */
final readonly class SendDirectMessageHandler
{
    public function __construct(
        private RegisteredUsers $users,
        private MessageAudience $audience,
        private UserBlockRepository $blocks,
        private ConversationRepository $conversations,
        private DirectMessageRepository $messages,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SendDirectMessage $command): SentMessage
    {
        try {
            $sender = MemberId::fromString($command->senderId);
            $recipient = MemberId::fromString($command->recipientId);
        } catch (InvalidValue) {
            throw ConversationNotFound::create();
        }

        if ($sender->value() === $recipient->value()) {
            throw MessageRefused::toYourself();
        }

        if (!$this->users->isActivated($command->recipientId)) {
            // Ni existe, ni está activada, ni se distingue cuál de las dos:
            // distinguirlo confirmaría quién está en la plataforma (`RN-7`).
            throw ConversationNotFound::create();
        }

        // El cuerpo se valida antes de mirar las puertas: es lo único que
        // quien escribe puede arreglar, y decírselo no revela nada.
        $body = MessageBody::fromString($command->body);

        if ($this->blocks->existsBetween($sender, $recipient)) {
            // Corta siempre, en los dos sentidos y esté la conversación
            // abierta o no (`RN-5`).
            throw MessageRefused::notAccepted();
        }

        $now = $this->clock->now();
        $conversation = $this->conversations->between($sender, $recipient);

        if (null === $conversation) {
            // Solo al abrirla (`RN-3`, `RN-4`).
            if (!$this->audience->acceptsMessagesFrom($command->recipientId, $command->senderId)) {
                throw MessageRefused::notAccepted();
            }

            $conversation = new Conversation(ConversationId::generate(), $sender, $recipient, $now);
        }

        $message = new DirectMessage(
            DirectMessageId::generate(),
            $conversation->id(),
            $sender,
            $body->value(),
            $now,
        );

        $this->session->execute(function () use ($conversation, $message, $now): void {
            $conversation->messageSent($message->id(), $now);
            $this->conversations->save($conversation);
            $this->messages->save($message);
        });

        $this->events->publish(new DirectMessageSent(
            EventId::generate(),
            $conversation->id()->value(),
            $sender->value(),
            $recipient->value(),
            $now,
        ));

        return new SentMessage($conversation->id()->value(), $message->id()->value(), $now);
    }
}
