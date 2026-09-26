<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\Repository;

use LectoresBeta\Community\Messaging\Domain\Entity\Conversation;
use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface ConversationRepository
{
    public function save(Conversation $conversation): void;

    public function ofId(ConversationId $id): ?Conversation;

    /**
     * La conversación de esos dos, en el orden que sea.
     *
     * El par se guarda ordenado, así que `(A,B)` y `(B,A)` son la misma fila
     * y **no hay dos conversaciones paralelas con la misma persona**.
     */
    public function between(MemberId $one, MemberId $two): ?Conversation;

    /**
     * Las de una persona, la más reciente primero.
     *
     * `$hiddenMemberIds` son aquellos con quienes hay bloqueo: la
     * conversación **no se borra** —el bloqueo se puede deshacer y el hilo
     * vuelve— pero deja de aparecer (`FEAT-COM-012` `RN-7`).
     *
     * Pagina **por cursor y no por fecha**, aunque la fecha sea lo que
     * ordena. Dos conversaciones pueden tener su último mensaje en el mismo
     * instante, y ordenar solo por él deja el desempate al azar: entre dos
     * páginas, eso es una conversación repetida o una que desaparece.
     *
     * @param list<string> $hiddenMemberIds
     *
     * @return list<Conversation> con una fila de más, que es cómo se sabe si
     *                            hay página siguiente
     */
    public function of(MemberId $member, array $hiddenMemberIds, ?Cursor $after, int $limit): array;
}
