<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Repository;

use LectoresBeta\Notification\Delivery\Domain\Entity\AuthorFollower;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;

/**
 * La copia del grafo de seguidores que hace falta para repartir un aviso
 * (`FEAT-NOT-004`).
 *
 * `followersOf` devuelve **identificadores y por páginas**, y las dos cosas
 * son deliberadas. Identificadores porque esto reparte, no enseña: quien
 * quiera pintar una lista de seguidores tiene su endpoint en `Community`, con
 * sus reglas de visibilidad. Y por páginas porque un autor con muchos
 * seguidores no cabe en memoria, y el día que no quepa no es el día de
 * descubrirlo.
 */
interface AuthorFollowerRepository
{
    /**
     * Quién sigue a este autor, en orden estable y con tope.
     *
     * Ordenado por identificador, que es lo que permite continuar donde se
     * quedó sin repetir ni saltarse a nadie aunque entre página y página
     * alguien empiece a seguir.
     *
     * @return list<string> identificadores de seguidor
     */
    public function followersOf(RecipientId $authorId, ?string $after, int $limit): array;

    public function between(RecipientId $followerId, RecipientId $authorId): ?AuthorFollower;

    public function save(AuthorFollower $follower): void;

    public function remove(AuthorFollower $follower): void;
}
