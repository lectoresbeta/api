<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Image;

/**
 * Normalizar una imagen que ha subido alguien.
 *
 * **Reescribe siempre el fichero**, y esa es la razón de que exista: no
 * valida y deja pasar, sino que decodifica y vuelve a codificar. Un
 * reencodificado no puede arrastrar metadatos, y los metadatos de una foto
 * llevan **dónde se tomó** (`file-uploads.md`). Que el navegador la haya
 * recortado antes no cambia nada: recortar y sanear son cosas distintas.
 *
 * Tampoco gira. La orientación EXIF es un problema de quien rota, y este
 * puerto no rota nada (`FEAT-USR-037` `RN-4b`).
 */
interface ImageProcessor
{
    /**
     * `null` cuando esos bytes no son una imagen que se pueda leer, que es la
     * respuesta a un fichero corrupto y a un `.jpg` que en realidad es otra
     * cosa. Quien llama decide qué error de negocio es cada caso.
     *
     * @param int $maxSide el lado mayor, en píxeles. Una imagen más pequeña
     *                     **no se agranda**: estirarla solo la emborrona
     */
    public function normalise(string $bytes, int $maxSide): ?ProcessedImage;

    /**
     * Lo mismo, pero además **cuadrada**.
     *
     * Recorta al centro lo que sobre del lado largo. No es el recorte del
     * usuario —ese ya lo hizo su navegador— sino la normalización de una
     * imagen que llega sin ser cuadrada, que es un fallo del cliente y no una
     * decisión de nadie.
     */
    public function normaliseSquare(string $bytes, int $side): ?ProcessedImage;
}
