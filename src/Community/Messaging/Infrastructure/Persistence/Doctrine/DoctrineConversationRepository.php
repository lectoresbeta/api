<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Messaging\Domain\Entity\Conversation;
use LectoresBeta\Community\Messaging\Domain\Repository\ConversationRepository;
use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Conversation>
 */
final class DoctrineConversationRepository extends DoctrineRepository implements ConversationRepository
{
    public function save(Conversation $conversation): void
    {
        $this->register($conversation);
    }

    public function ofId(ConversationId $id): ?Conversation
    {
        return $this->repository()->find($id->value());
    }

    public function between(MemberId $one, MemberId $two): ?Conversation
    {
        // El par se guarda ordenado, así que ordenarlo aquí es lo que hace
        // que `(A,B)` y `(B,A)` encuentren la misma fila.
        $pair = [$one->value(), $two->value()];
        sort($pair);

        return $this->repository()->findOneBy(['memberOne' => $pair[0], 'memberTwo' => $pair[1]]);
    }

    public function of(MemberId $member, array $hiddenMemberIds, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('c')
            ->where('c.memberOne = :me OR c.memberTwo = :me')
            ->setParameter('me', $member->value());

        if ([] !== $hiddenMemberIds) {
            // Con quien hay bloqueo no aparece, esté en la columna que esté.
            $query
                ->andWhere('c.memberOne NOT IN (:hidden) AND c.memberTwo NOT IN (:hidden)')
                ->setParameter('hidden', $hiddenMemberIds);
        }

        if (null !== $after) {
            $query
                ->andWhere('(c.lastMessageAt < :at OR (c.lastMessageAt = :at AND c.lastMessageId < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<Conversation> $found */
        $found = $query
            // Desempata **el último mensaje**, no la conversación: las
            // fechas se guardan al segundo, y el id de la conversación es el
            // de cuando se abrió, así que un hilo antiguo que acaba de
            // recibir algo quedaría por debajo de uno recién abierto.
            //
            // Va en las dos mitades —el orden y la condición del cursor—
            // porque con una sola la página siguiente no empieza donde acabó
            // la anterior.
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.lastMessageId', 'DESC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }

    protected function entityClass(): string
    {
        return Conversation::class;
    }
}
