<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Storage;

/**
 * Dónde viven los ficheros, que **nunca es la base de datos**
 * (`file-uploads.md`).
 *
 * El puerto vive en `Shared` porque guardar un fichero es una capacidad
 * técnica genérica, como el reloj: la necesitan los avatares, las portadas y
 * algún día los manuscritos, y ninguna de esas tres cosas es más dueña de
 * ella que las otras. Lo que **no** es genérico —qué tamaño, qué formatos,
 * qué se hace al borrar— se queda en el contexto que sube el fichero.
 *
 * La clave la elige quien guarda, y conviene que sea **imposible de
 * adivinar**: un identificador correlativo convertiría el almacén en algo que
 * se puede recorrer.
 */
interface FileStorage
{
    public function put(string $key, StoredFile $file): void;

    public function read(string $key): ?StoredFile;

    /**
     * Borrar lo que ya no está **no es un error**: el estado que se pedía ya
     * se cumple, y quien borra dos veces suele ser un reintento.
     */
    public function delete(string $key): void;
}
