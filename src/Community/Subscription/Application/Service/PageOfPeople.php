<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Service;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Application\DTO\SubscriptionPage;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;
use LectoresBeta\Community\Subscription\Domain\Exception\ProfileListNotVisible;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\VisibleProfiles;

/**
 * Lo que las dos listas hacen igual (`FEAT-COM-027`).
 *
 * Seguidos y seguidores son la misma tabla leída por sus dos extremos, así
 * que paginarlas, comprobar el perfil del titular y filtrar las filas es el
 * mismo trabajo a los dos lados. Escribirlo dos veces serían dos sitios donde
 * equivocarse con el cursor y, peor, dos sitios donde olvidar el filtro.
 *
 * **`Community` no decide quién es visible.** Le pregunta a `User`, que es
 * quien tiene los ajustes de privacidad, el grafo de seguidores y el estado
 * de la cuenta; y le pregunta por la página entera de una vez, porque una
 * llamada por fila sería un N+1 escondido detrás de un contrato.
 */
final readonly class PageOfPeople
{
    public function __construct(private VisibleProfiles $profiles)
    {
    }

    public function size(?int $requested): int
    {
        return PageSize::of($requested);
    }

    public function after(?string $cursor): ?Cursor
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        return Cursor::decode($cursor) ?? throw InvalidCursor::create();
    }

    /**
     * El titular, si quien pregunta puede verle.
     *
     * `404` y no `403` cuando no: un `403` confirmaría que la cuenta está
     * ahí, y el ajuste que la esconde existe precisamente para que no se
     * pueda confirmar. Un identificador que ni siquiera tiene forma de
     * identificador responde lo mismo, por lo mismo.
     */
    public function owner(string $userId, ?string $viewerId): MemberId
    {
        try {
            $owner = MemberId::fromString($userId);
        } catch (InvalidValue) {
            throw ProfileListNotVisible::create();
        }

        if ([] === $this->profiles->visibleTo($viewerId, [$owner->value()])) {
            throw ProfileListNotVisible::create();
        }

        return $owner;
    }

    /**
     * @param list<AuthorSubscription>             $found    una fila de más que la página, que es
     *                                                       cómo se sabe si hay siguiente
     * @param \Closure(AuthorSubscription): string $personOf qué extremo de la suscripción se enseña
     */
    public function of(array $found, int $limit, ?string $viewerId, \Closure $personOf): SubscriptionPage
    {
        $rows = \array_slice($found, 0, $limit);
        $last = end($rows);

        $cards = $this->profiles->visibleTo($viewerId, array_values(array_unique(
            array_map($personOf, $rows),
        )));

        $people = [];

        foreach ($rows as $subscription) {
            $card = $cards[$personOf($subscription)] ?? null;

            // Quien no sea visible deja un hueco, y la página se queda más
            // corta. Es lo que `RN-5` advierte: lo que dice si hay más es el
            // cursor, no cuántas filas llegaron.
            if (null === $card) {
                continue;
            }

            $people[] = ['profile' => $card, 'subscribedAt' => $subscription->createdAt()];
        }

        return new SubscriptionPage(
            $people,
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->createdAt(), $last->id()->value())->encode()
                : null,
        );
    }
}
