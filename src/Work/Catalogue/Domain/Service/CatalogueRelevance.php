<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Domain\Service;

/**
 * Cómo ordena el catálogo (`decision:0008`).
 *
 * ```text
 * puntuación = capacidad × desatención × frescura
 *
 * capacidad   = correcciones que el autor puede pagar, con tope de 10
 * desatención = 1 ÷ (1 + correcciones recibidas)
 * frescura    = 1 ÷ (1 + semanas abierta a corrección)^0.5
 * ```
 *
 * **Ninguno de los tres factores es la popularidad, y es deliberado.** Esta
 * plataforma no existe para que se lean obras sino para que se corrijan; un
 * catálogo que pusiera delante lo más popular concentraría las correcciones
 * en pocos textos y dejaría a la mayoría de los autores sin ninguna.
 *
 * La propiedad que lo sostiene: **aparecer arriba consume lo que te puso
 * arriba**. Cada corrección recibida gasta saldo del autor —baja la
 * capacidad— y sube su contador —baja la desatención—, así que la obra
 * desciende sola y deja sitio a otra.
 *
 * Las constantes viven aquí y la aritmética la hace PostgreSQL, porque
 * ordenar y paginar sin la base de datos significa traerse el catálogo
 * entero a memoria. Lo que garantiza que las dos versiones no se separen es
 * que **solo hay una**: esta clase no calcula, declara.
 */
final class CatalogueRelevance
{
    /**
     * El tope de capacidad. Lo aplica `Credits` antes de publicarlo
     * —`affordableCorrections` llega ya acotado— y se declara aquí porque es
     * parte de esta fórmula.
     */
    public const CAPACITY_CAP = 10;

    /**
     * La raíz cuadrada de `decision:0008`. Con exponente 1 una obra de dos
     * meses caería demasiado deprisa; con 0 no caería nunca.
     */
    public const FRESHNESS_EXPONENT = 0.5;

    public const SECONDS_PER_WEEK = 604800;

    /**
     * Una obra sin ningún capítulo corregible puntúa **cero**, y por eso hay
     * un `CASE` antes de la fórmula: enseñar algo que el lector no puede
     * corregir desperdicia el sitio más valioso de la pantalla.
     */
    public const UNCORRECTABLE_SCORE = 0;
}
