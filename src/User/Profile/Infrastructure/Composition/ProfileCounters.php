<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Composition;

use LectoresBeta\Community\Recommendation\Application\Contract\TipsReceived;
use LectoresBeta\Community\Subscription\Application\Contract\SubscriptionCount;
use LectoresBeta\Community\Subscription\Application\Contract\SubscriptionCounts;
use LectoresBeta\Feedback\Correction\Application\Contract\DeliveredCorrectionCount;
use LectoresBeta\Work\Manuscript\Application\Contract\AuthoredWorkCount;
use Psr\Log\LoggerInterface;

/**
 * Las cifras de la cabecera del perfil (`FEAT-USR-028`, `FEAT-USR-014`).
 *
 * **Ninguna es de `User`.** Seguidos y seguidores son de `Community`, los
 * relatos de `Work`, las correcciones de `Feedback` y las propinas recibidas
 * también de `Community`, y cada uno responde por su contrato publicado
 * (`RN-1`).
 *
 * Que las propinas las conteste `Community` y no `Credits` merece una línea:
 * los créditos son de `Credits`, pero ese contexto **no publica contratos**
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)).
 * Narra sus hechos, y `Community` ya mantiene esa cifra desde
 * `CorrectionTipped` para su propia reputación; la puerta se abre donde ya
 * está el dato y no donde está su origen.
 *
 * Aquí no se consulta ni una tabla ajena; de hecho aquí no se calcula nada —
 * esta clase **solo ensambla** (`RN-2`), y por eso vive en `Infrastructure` y
 * no en `Application`: componer la respuesta de varias fuentes es trabajo de
 * la frontera, no del caso de uso.
 *
 * **Un contador caído no tumba la pantalla** (`RN-3`). Si un contexto falla,
 * su cifra viaja como `null` —«no se ha podido saber», que no es lo mismo que
 * cero— y el perfil se pinta igual. Es la decisión que hace que la cabecera
 * del perfil no dependa de que los tres contextos estén sanos a la
 * vez, y la que separa un número ausente de un número que de verdad es cero.
 *
 * El fallo se registra, porque es un fallo: lo que no hace es propagarse.
 *
 * `P-12`, resuelta por ahora: se componen **en cada petición**, tres consultas
 * de conteo sobre índices. Un read model alimentado por eventos es la
 * alternativa, y llegará cuando las cifras digan que hace falta, no antes —
 * traería su propia deuda, que es un contador que puede quedarse atrás.
 */
final readonly class ProfileCounters
{
    public function __construct(
        private SubscriptionCounts $subscriptions,
        private AuthoredWorkCount $works,
        private DeliveredCorrectionCount $corrections,
        private TipsReceived $tips,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{following: ?int, followers: ?int, works: ?int, corrections: ?int, tips: ?int}
     */
    public function of(string $userId): array
    {
        // Seguidos y seguidores vienen de una sola pregunta: se enseñan
        // juntos, y dos viajes para dos números pegados no tendría sentido.
        $subscriptions = $this->attempt(
            'subscriptions',
            fn (): SubscriptionCount => $this->subscriptions->of($userId),
        );

        return [
            'following' => $subscriptions?->following,
            'followers' => $subscriptions?->followers,
            'works' => $this->attempt('works', fn (): int => $this->works->ofAuthor($userId)),
            'corrections' => $this->attempt('corrections', fn (): int => $this->corrections->ofReader($userId)),
            'tips' => $this->attempt('tips', fn (): int => $this->tips->ofReader($userId)),
        ];
    }

    /**
     * @template T
     *
     * @param \Closure(): T $ask
     *
     * @return T|null
     */
    private function attempt(string $counter, \Closure $ask): mixed
    {
        try {
            return $ask();
        } catch (\Throwable $failure) {
            // El detalle va al log y nunca a la respuesta: fuera solo viaja
            // que esa cifra no está.
            $this->logger->error('A profile counter could not be read.', [
                'counter' => $counter,
                'exception' => $failure,
            ]);

            return null;
        }
    }
}
