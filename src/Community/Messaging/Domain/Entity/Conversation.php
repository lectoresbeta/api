<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\Entity;

use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Messaging\Domain\ValueObject\DirectMessageId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * A thread between two people.
 *
 * The pair is stored sorted so that (A,B) and (B,A) are the same row and the
 * unique index means one conversation per pair.
 *
 * Whether the recipient accepts direct messages is a `User` setting
 * (`FEAT-USR-038`) that this context consumes; it is checked when sending,
 * not stored here.
 */
class Conversation
{
    private string $id;

    private string $memberOne;

    private string $memberTwo;

    private \DateTimeImmutable $startedAt;

    private \DateTimeImmutable $lastMessageAt;

    /**
     * El último mensaje, y está aquí **para ordenar la lista**.
     *
     * Las fechas se guardan al segundo en todo el proyecto, así que dos
     * conversaciones que reciben un mensaje en el mismo segundo empatan. En
     * el resto de listas ese empate lo rompe el identificador de la propia
     * fila, y sale bien porque ahí id y fecha crecen juntos: una publicación
     * más nueva tiene id más nuevo.
     *
     * **Aquí no.** Lo que ordena es el último mensaje, y el id de la
     * conversación es el de cuando se abrió: un hilo antiguo que acaba de
     * recibir algo quedaría por debajo de uno recién abierto. El id del
     * mensaje sí crece con el tiempo (UUIDv7), así que es el desempate
     * correcto — y el que hace exacto el cursor.
     */
    private ?string $lastMessageId = null;

    public function __construct(
        ConversationId $id,
        MemberId $one,
        MemberId $two,
        \DateTimeImmutable $now,
    ) {
        $pair = [$one->value(), $two->value()];
        sort($pair);

        $this->id = $id->value();
        $this->memberOne = $pair[0];
        $this->memberTwo = $pair[1];
        $this->startedAt = $now;
        $this->lastMessageAt = $now;
    }

    public function id(): ConversationId
    {
        return ConversationId::fromString($this->id);
    }

    public function includes(MemberId $member): bool
    {
        return \in_array($member->value(), [$this->memberOne, $this->memberTwo], true);
    }

    /**
     * La otra parte. Lo pregunta la lista, que enseña con quién es cada
     * conversación y no el par entero.
     */
    public function otherThan(MemberId $member): MemberId
    {
        return MemberId::fromString($member->value() === $this->memberOne ? $this->memberTwo : $this->memberOne);
    }

    public function lastMessageAt(): \DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    /**
     * Nulo solo en una conversación anterior a que esto existiera y sin
     * mensajes; con uno, la migración lo rellenó.
     */
    public function lastMessageId(): ?DirectMessageId
    {
        return null === $this->lastMessageId ? null : DirectMessageId::fromString($this->lastMessageId);
    }

    public function messageSent(DirectMessageId $messageId, \DateTimeImmutable $now): void
    {
        $this->lastMessageAt = $now;
        $this->lastMessageId = $messageId->value();
    }
}
