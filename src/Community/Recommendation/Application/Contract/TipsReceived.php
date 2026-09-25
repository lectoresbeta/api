<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Contract;

/**
 * Cuántos créditos ha recibido alguien en propinas
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Para el contador «recibidos en propinas» del perfil (`FEAT-CRD-017`
 * `RN-3c`). **Una sola cifra agregada**, nunca la lista: exponer quién recibe
 * reconocimiento y quién no desanima al corrector novato, y por eso la
 * propina no se publica corrección a corrección.
 *
 * **Lo publica `Community` y no `Credits`**, y esa es la parte que hay que
 * entender. Los créditos son de `Credits`, pero ese contexto no publica
 * contratos
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)):
 * narra sus hechos y cada cual proyecta lo que necesita. `Community` ya
 * mantiene esta cifra desde `CorrectionTipped` para su propia reputación, así
 * que la puerta se abre donde ya está el dato y no donde está su origen.
 *
 * Es la señal de calidad más fiable de la plataforma, porque es la única que
 * alguien ha **pagado de su bolsillo**.
 */
interface TipsReceived
{
    public function ofReader(string $readerId): int;
}
