<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Storage;

use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Application\Storage\StoredFile;

/**
 * Los ficheros, en el disco de la máquina.
 *
 * Es la implementación con la que se empieza, no la definitiva: el día que
 * haya más de una instancia sirviendo la API, un directorio local deja de
 * valer y hay que cambiar esta clase por un adaptador de almacenamiento de
 * objetos. **Que sea un cambio de una sola clase es la razón de que el puerto
 * exista.**
 *
 * El tipo de cada fichero se guarda **al lado**, en un `.type`, y no se
 * adivina por la extensión al leerlo. Adivinarlo es como se acaba sirviendo
 * una imagen como `text/html`.
 */
final readonly class LocalFileStorage implements FileStorage
{
    public function __construct(private string $directory)
    {
    }

    public function put(string $key, StoredFile $file): void
    {
        $path = $this->pathOf($key);
        $folder = \dirname($path);

        if (!is_dir($folder) && !mkdir($folder, 0o775, true) && !is_dir($folder)) {
            throw new \RuntimeException('The storage directory could not be created.');
        }

        if (false === file_put_contents($path, $file->contents)) {
            throw new \RuntimeException('The file could not be stored.');
        }

        file_put_contents($path.'.type', $file->contentType);
    }

    public function read(string $key): ?StoredFile
    {
        $path = $this->pathOf($key);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            return null;
        }

        $type = is_file($path.'.type') ? file_get_contents($path.'.type') : false;

        return new StoredFile($contents, false === $type || '' === $type ? 'application/octet-stream' : $type);
    }

    public function delete(string $key): void
    {
        $path = $this->pathOf($key);

        foreach ([$path, $path.'.type'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * La clave se comprueba **aquí y no en quien llama**: es la única
     * frontera por la que un `../` llegaría al sistema de ficheros, y una
     * comprobación que vive en el llamante es una comprobación que algún día
     * falta.
     */
    private function pathOf(string $key): string
    {
        if (1 !== preg_match('#^[a-z0-9][a-z0-9/_.-]{0,254}$#', $key) || str_contains($key, '..')) {
            throw new \InvalidArgumentException('That is not a storage key.');
        }

        return rtrim($this->directory, '/').'/'.$key;
    }
}
