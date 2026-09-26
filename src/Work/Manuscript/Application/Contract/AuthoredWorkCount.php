<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Contract;

/**
 * Cuántas obras ha escrito alguien
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Para el contador «Relatos» de la cabecera del perfil (`FEAT-USR-028`). Una
 * cifra, nunca la lista: «Mis relatos» es una pestaña paginada con sus
 * propios filtros y su propia autorización (`FEAT-WRK-015`), y entregar las
 * obras aquí sería servir borradores ajenos por una puerta lateral.
 *
 * **Cuenta todas las obras de esa persona, sea cual sea su estado.** Es el
 * número correcto para su propio perfil, que es donde se usa hoy: el autor
 * sabe cuántas tiene y sus borradores son suyos. El día que este contador
 * aparezca en el perfil que ven los demás hará falta **otra pregunta**, no
 * esta — contar allí los borradores diría cuánto tiene alguien sin publicar.
 */
interface AuthoredWorkCount
{
    public function ofAuthor(string $authorId): int;
}
