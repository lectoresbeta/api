<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Exception;

/**
 * Un fallo que sabe **cuándo** se puede volver a intentar.
 *
 * Existe para que un `429` lleve su cabecera `Retry-After`, que es la forma
 * que entienden los clientes HTTP, los proxies y las bibliotecas de
 * reintento sin que nadie las programe. Un cuerpo JSON con la misma cifra
 * solo lo entiende quien lo haya leído.
 *
 * Es aparte de `FailureDetails` porque responde a otra pregunta: aquel dice
 * qué más necesita saber quien llama, este dice cuánto tiene que esperar. Un
 * fallo puede implementar los dos, y lo normal es que el segundo repita la
 * cifra en el cuerpo para quien no mire las cabeceras.
 */
interface RetryAfter
{
    /**
     * Segundos hasta el próximo intento posible. Nunca negativo: un
     * `Retry-After` en el pasado es peor que ninguno, porque invita a
     * reintentar de inmediato.
     */
    public function retryAfterSeconds(): int;
}
