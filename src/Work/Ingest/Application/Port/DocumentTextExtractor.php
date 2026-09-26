<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Application\Port;

use LectoresBeta\Work\Ingest\Domain\ValueObject\ExtractedDocument;

/**
 * Sacar el texto de un fichero que alguien ha subido (`FEAT-WRK-002`).
 *
 * Un puerto porque **leer un formato de fichero es infraestructura**: el caso
 * de uso sabe que hay párrafos y títulos, y no tiene por qué saber que uno de
 * los formatos es un zip con XML dentro.
 *
 * Devuelve **párrafos y qué es un título**, no una cadena. La diferencia es
 * lo que hace posible proponer un troceado: con el texto aplanado no queda
 * nada de la estructura que el autor ya había puesto en su documento, y
 * habría que adivinarla con heurísticas sobre líneas en blanco.
 *
 * Qué formatos entiende es cosa de la implementación. Añadir `.pdf` o el
 * `.doc` binario es escribir otro adaptador, que es exactamente para lo que
 * está este puerto.
 */
interface DocumentTextExtractor
{
    /**
     * @param string $bytes    el fichero tal y como llegó
     * @param string $filename el nombre original, **solo como pista** del
     *                         formato: quien decide es el contenido
     */
    public function extract(string $bytes, string $filename): ExtractedDocument;
}
