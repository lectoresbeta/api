<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Entity\AuthorFollower;

/**
 * La copia del grafo de seguidores que `User` necesita para resolver las
 * audiencias `FOLLOWERS` (`FEAT-COM-010`).
 *
 * La pregunta que importa es una sola y devuelve un booleano. Listar
 * seguidores no está aquí a propósito: esas listas son de `Community`
 * (`FEAT-COM-027`), y esta copia existe para decidir, no para enseñar.
 */
interface AuthorFollowerRepository
{
    public function follows(UserId $followerId, UserId $authorId): bool;

    /**
     * A cuáles de estos sigue, en una consulta.
     *
     * Para resolver una página entera de una lista (`FEAT-COM-027`).
     * Preguntar fila a fila sería una consulta por persona enseñada.
     *
     * @param list<string> $authorIds
     *
     * @return list<string> los que sí, sin orden prometido
     */
    public function followedAmong(UserId $followerId, array $authorIds): array;

    public function between(UserId $followerId, UserId $authorId): ?AuthorFollower;

    public function save(AuthorFollower $follower): void;

    public function remove(AuthorFollower $follower): void;

    /**
     * Cuántas personas siguen a alguien, y a cuántas sigue (`FEAT-USR-014`).
     *
     * Se cuentan aquí y no se proyectan en un contador aparte porque este
     * contexto ya tiene el grafo entero: un número copiado de algo que ya se
     * tiene solo puede desviarse.
     */
    public function countFollowersOf(UserId $authorId): int;

    public function countFollowedBy(UserId $followerId): int;
}
