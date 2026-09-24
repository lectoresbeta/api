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

    public function between(UserId $followerId, UserId $authorId): ?AuthorFollower;

    public function save(AuthorFollower $follower): void;

    public function remove(AuthorFollower $follower): void;
}
