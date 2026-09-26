<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Domain\Enum;

/**
 * Dónde se ha mencionado a alguien (`FEAT-COM-032`).
 *
 * Una sola tabla para los dos sitios, y no una por cada uno, porque
 * «dónde me han mencionado» es **una** pregunta: con dos tablas, cada quien
 * que la haga tendrá que acordarse de unirlas, y el día que aparezca un
 * tercer sitio donde mencionar, de unir tres.
 */
enum MentionSubject: string
{
    case POST = 'POST';
    case COMMENT = 'COMMENT';
}
