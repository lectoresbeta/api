<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Messaging\Domain\Entity\DirectMessage;
use LectoresBeta\Community\Messaging\Domain\Repository\DirectMessageRepository;
use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<DirectMessage>
 */
final class DoctrineDirectMessageRepository extends DoctrineRepository implements DirectMessageRepository
{
    public function save(DirectMessage $message): void
    {
        $this->register($message);
    }

    public function of(ConversationId $conversationId, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('m')
            ->where('m.conversationId = :conversation')
            ->setParameter('conversation', $conversationId->value())
            ->orderBy('m.sentAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults($limit + 1);

        if (null !== $after) {
            $query
                ->andWhere('(m.sentAt < :at OR (m.sentAt = :at AND m.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<DirectMessage> $found */
        $found = $query->getQuery()->getResult();

        return $found;
    }

    public function lastOf(ConversationId $conversationId): ?DirectMessage
    {
        return $this->of($conversationId, null, 1)[0] ?? null;
    }

    public function unreadFor(ConversationId $conversationId, MemberId $member): int
    {
        return $this->unreadAmong([$conversationId->value()], $member)[$conversationId->value()] ?? 0;
    }

    public function unreadAmong(array $conversationIds, MemberId $member): array
    {
        if ([] === $conversationIds) {
            return [];
        }

        /** @var list<array{conversationId: string, unread: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m.conversationId AS conversationId', 'COUNT(m.id) AS unread')
            ->from(DirectMessage::class, 'm')
            ->where('m.conversationId IN (:conversations)')
            // Lo recibido, nunca lo enviado: marcar como leído lo propio no
            // significa nada.
            ->andWhere('m.senderId <> :me')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('conversations', $conversationIds)
            ->setParameter('me', $member->value())
            ->groupBy('m.conversationId')
            ->getQuery()
            ->getResult();

        $unread = [];

        foreach ($rows as $row) {
            $unread[$row['conversationId']] = (int) $row['unread'];
        }

        return $unread;
    }

    public function markReadFor(ConversationId $conversationId, MemberId $member, \DateTimeImmutable $now): void
    {
        // Una sentencia y no una fila cada vez: una conversación larga son
        // cientos de mensajes, y cargarlos todos para poner una fecha sería
        // pagar el hilo entero por vaciar un contador.
        $this->entityManager->createQueryBuilder()
            ->update(DirectMessage::class, 'm')
            ->set('m.readAt', ':now')
            ->where('m.conversationId = :conversation')
            ->andWhere('m.senderId <> :me')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('conversation', $conversationId->value())
            ->setParameter('me', $member->value())
            ->getQuery()
            ->execute();
    }

    public function lastAmong(array $conversationIds): array
    {
        if ([] === $conversationIds) {
            return [];
        }

        /** @var list<DirectMessage> $messages */
        $messages = $this->repository()->createQueryBuilder('m')
            ->where('m.conversationId IN (:conversations)')
            ->setParameter('conversations', $conversationIds)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();

        $last = [];

        foreach ($messages as $message) {
            // El último gana porque vienen en orden ascendente.
            $last[$message->conversationId()->value()] = $message;
        }

        return $last;
    }

    protected function entityClass(): string
    {
        return DirectMessage::class;
    }
}
