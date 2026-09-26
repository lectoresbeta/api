<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Repository;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface AuthorSubscriptionRepository
{
    public function between(MemberId $subscriberId, MemberId $authorId): ?AuthorSubscription;

    /**
     * A quién sigue esta persona, de lo más reciente a lo más antiguo
     * (`FEAT-COM-027`).
     *
     * Devuelve **una fila de más que el límite**, que es como se sabe si hay
     * página siguiente sin contar nada.
     *
     * @return list<AuthorSubscription>
     */
    public function subscriptionsOf(MemberId $subscriberId, ?Cursor $after, int $limit): array;

    /**
     * Quién sigue a esta persona, con el mismo orden y el mismo truco.
     *
     * @return list<AuthorSubscription>
     */
    public function subscribersOf(MemberId $authorId, ?Cursor $after, int $limit): array;

    /**
     * A quién sigue, entera y sin paginar (`FEAT-COM-001`).
     *
     * Existe para **una** cosa: resolver en la consulta del muro qué
     * publicaciones `FOLLOWERS` alcanza quien mira. No sirve para pintar una
     * lista —para eso está `subscriptionsOf`, que pagina— y no debe usarse
     * para eso: lo que aquí es aceptable porque acaba en un `IN` de la base
     * de datos, allí sería una lista sin límite viajando hasta el cliente.
     *
     * @return list<string> identificadores de autor
     */
    public function followedBy(MemberId $subscriberId): array;

    /**
     * A cuántos sigue, y cuántos le siguen (`FEAT-USR-028`).
     *
     * Contar y no traer las filas: un contador de la cabecera del perfil no
     * puede costar cargar una lista que crece sin límite.
     */
    public function countSubscriptionsOf(MemberId $subscriberId): int;

    public function countSubscribersOf(MemberId $authorId): int;

    public function save(AuthorSubscription $subscription): void;

    public function remove(AuthorSubscription $subscription): void;
}
