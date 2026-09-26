<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Notification>
 */
final class DoctrineNotificationRepository extends DoctrineRepository implements NotificationRepository
{
    public function save(Notification $notification): void
    {
        $this->register($notification);
    }

    public function existsFor(RecipientId $recipientId, NotificationKind $kind, string $sourceEventId): bool
    {
        return null !== $this->ofSource($recipientId, $kind, $sourceEventId);
    }

    public function ofSource(RecipientId $recipientId, NotificationKind $kind, string $sourceEventId): ?Notification
    {
        return $this->repository()->findOneBy([
            'recipientId' => $recipientId->value(),
            'kind' => $kind,
            'sourceEventId' => $sourceEventId,
        ]);
    }

    public function ofId(NotificationId $id): ?Notification
    {
        return $this->repository()->find($id->value());
    }

    public function inboxOf(RecipientId $recipientId, bool $unreadOnly, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('n')
            ->where('n.recipientId = :recipient')
            // Lo silenciado no se enseña (`FEAT-NOT-003` `RN-1`). La fila
            // existe para poder anotar si salió por correo, no para acabar
            // en la campana que su dueño apagó.
            ->andWhere('n.inbox = true')
            ->setParameter('recipient', $recipientId->value());

        if ($unreadOnly) {
            $query->andWhere('n.readAt IS NULL');
        }

        if (null !== $after) {
            $query
                ->andWhere('(n.createdAt < :at OR (n.createdAt = :at AND n.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<Notification> $found */
        $found = $query
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }

    public function countUnread(RecipientId $recipientId): int
    {
        return $this->repository()->count([
            'recipientId' => $recipientId->value(),
            'inbox' => true,
            'readAt' => null,
        ]);
    }

    public function markReadByCorrection(RecipientId $recipientId, string $correctionId, \DateTimeImmutable $now): void
    {
        // `payload` es `jsonb` y esto es una consulta que DQL no sabe
        // expresar, así que es SQL de PostgreSQL — y por eso vive aquí.
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE notification_ctx.notification
                SET read_at = :now
              WHERE recipient_id = :recipient
                AND inbox = TRUE
                AND read_at IS NULL
                AND payload->>'correctionId' = :correction",
            ['now' => $now->format('Y-m-d H:i:s'), 'recipient' => $recipientId->value(), 'correction' => $correctionId],
        );

        // La actualización pasa por encima de la unidad de trabajo, así que lo
        // que Doctrine tenga cargado se queda con la versión vieja.
        $this->entityManager->clear();
    }

    public function markAllRead(RecipientId $recipientId, \DateTimeImmutable $now): void
    {
        // Una sentencia, no una fila cada vez: quien lleva meses sin entrar
        // puede tener cientos de avisos, y cargarlos todos para marcarlos
        // sería pagar la bandeja entera por vaciar un contador.
        $this->entityManager->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.readAt', ':now')
            ->where('n.recipientId = :recipient')
            ->andWhere('n.inbox = true')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('recipient', $recipientId->value())
            ->getQuery()
            ->execute();

        // La actualización masiva pasa por encima de la unidad de trabajo, así
        // que lo que Doctrine tenga cargado se queda con la versión vieja.
        $this->entityManager->clear();
    }

    protected function entityClass(): string
    {
        return Notification::class;
    }
}
