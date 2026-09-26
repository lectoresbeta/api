<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Ranking\Domain\Entity\AuthorStats;
use LectoresBeta\Community\Recommendation\Application\DTO\AuthorSuggestion;
use LectoresBeta\Community\Recommendation\Application\DTO\AuthorSuggestions;
use LectoresBeta\Community\Recommendation\Application\Query\ListAuthorSuggestions;
use LectoresBeta\Community\Recommendation\Domain\Repository\AuthorSuggestionRepository;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\User\Account\Application\Contract\ActiveAccounts;
use LectoresBeta\User\Account\Application\Contract\VisibleProfiles;

/**
 * A quién proponer seguir (`FEAT-COM-016`).
 *
 * El caso normal de los primeros meses **no es la lista llena, es la lista
 * corta**: en una plataforma recién lanzada no hay autores que sugerir, y ese
 * es justamente el momento en que todo el mundo pasa por aquí. De ahí la
 * cadena de relleno y, si aun así no llega, la omisión del paso entero.
 *
 * Quien decide omitirlo es el servidor (`RN-10`). Si lo decidiera el cliente
 * habría dos sitios donde cambiar el umbral y uno se quedaría atrás.
 */
final readonly class ListAuthorSuggestionsHandler
{
    public function __construct(
        private AuthorSuggestionRepository $suggestions,
        private AuthorSubscriptionRepository $subscriptions,
        private VisibleProfiles $profiles,
        private ActiveAccounts $accounts,
        private int $minimum,
        private int $maximum,
    ) {
    }

    public function __invoke(ListAuthorSuggestions $query): AuthorSuggestions
    {
        $member = MemberId::fromString($query->memberId);
        $genres = $this->suggestions->genresOf($member);
        $following = $this->subscriptions->followedBy($member);

        // El bloque del muro existe para quien no sigue a nadie, y desaparece
        // en cuanto sigue a alguien (`FEAT-COM-018` `RN-1`, `RN-6`). No es un
        // criterio de sugerencia distinto: es una condición de dónde se pinta.
        if ($query->onlyIfFollowingNobody && [] !== $following) {
            return new AuthorSuggestions(false, 'ALREADY_FOLLOWING_SOMEBODY', []);
        }

        $howMany = $query->howMany ?? $this->maximum;

        // Ni uno mismo ni a quien ya se sigue (`RN-3`, `RN-4`).
        $excluded = [$member->value(), ...$following];

        $found = $this->suggestions->byGenres($genres, $excluded, $howMany);

        // Segundo tramo: los más seguidos, sin filtrar por género. Es
        // preferible proponer autores populares aunque no encajen que enseñar
        // una pantalla casi vacía.
        if (\count($found) < $this->minimum) {
            $seen = array_map(static fn (AuthorStats $one): string => $one->authorId()->value(), $found);

            foreach ($this->suggestions->mostFollowed([...$excluded, ...$seen], $howMany - \count($found)) as $extra) {
                $found[] = $extra;
            }
        }

        if (\count($found) < $this->minimum) {
            // Dos silencios que significan cosas distintas: no hay gente, o
            // ya la sigues toda. La interfaz no dice lo mismo en cada caso.
            $candidates = $this->suggestions->countCandidates([$member->value()]);

            return new AuthorSuggestions(
                false,
                $candidates >= $this->minimum ? 'ALREADY_FOLLOWING_ALL' : 'NOT_ENOUGH_AUTHORS',
                [],
            );
        }

        return new AuthorSuggestions(true, null, $this->describe($found, $genres, $query->memberId));
    }

    /**
     * @param list<AuthorStats> $found
     * @param list<string>      $genres
     *
     * @return list<AuthorSuggestion>
     */
    private function describe(array $found, array $genres, string $memberId): array
    {
        $ids = array_map(static fn (AuthorStats $one): string => $one->authorId()->value(), $found);
        $matched = $this->suggestions->matchedGenres($ids, $genres);

        // El nombre y el avatar se piden a `User`, que es quien los tiene al
        // día: la proyección los guarda para ordenar y para no quedarse
        // vacía, no para ser la verdad sobre cómo se llama alguien.
        $cards = $this->profiles->visibleTo($memberId, $ids);

        // Y `RN-7`: solo cuentas en uso. Es una pregunta distinta de la
        // anterior y por eso son dos contratos — de alguien suspendido se
        // siguen leyendo sus comentarios, y en cambio no hay que proponer
        // seguirlo. La proyección de este contexto no conoce el estado de una
        // cuenta, ni debería: es de `User`.
        $active = array_flip($this->accounts->activeAmong($ids));

        $suggestions = [];

        foreach ($found as $author) {
            $id = $author->authorId()->value();
            $card = $cards[$id] ?? null;

            // Quien ha dejado de ser visible para quien mira no se sugiere:
            // proponer seguir a alguien cuyo perfil no se puede abrir sería
            // proponer un callejón sin salida. Y quien no tiene la cuenta en
            // uso tampoco: sin activar no puede publicar, y expulsado no
            // volverá a hacerlo.
            if (null === $card || !isset($active[$id])) {
                continue;
            }

            $suggestions[] = new AuthorSuggestion(
                $id,
                $card->name,
                $card->avatarUrl,
                $author->followers(),
                $author->publishedWorks(),
                $matched[$id] ?? [],
                false,
            );
        }

        return $suggestions;
    }
}
