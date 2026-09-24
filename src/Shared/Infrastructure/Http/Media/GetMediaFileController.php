<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Http\Media;

use LectoresBeta\Shared\Application\Storage\FileStorage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `GET /api/v1/media/{key}`: servir un fichero guardado.
 *
 * **Solo sirve lo que es público por naturaleza**, y hoy eso son los avatares
 * recortados: la imagen que su dueño eligió enseñar, que aparece en perfiles
 * abiertos sin sesión. Por eso no pide autenticación — exigirla haría
 * imposible pintar un perfil público.
 *
 * Y por eso comprueba el prefijo. Lo que no está bajo una carpeta pública no
 * se sirve **aunque se conozca su clave**: las fotos originales viven en otra
 * carpeta, y su único camino es el endpoint que comprueba de quién son
 * (`FEAT-USR-037`). Una lista de prefijos es una frontera que se lee de un
 * vistazo; confiar solo en que las claves son impredecibles deja la puerta
 * abierta al día que una se filtre por un log.
 */
#[AsController]
final readonly class GetMediaFileController
{
    /** Lo que se sirve en abierto. Añadir una carpeta aquí es una decisión. */
    private const PUBLIC_FOLDERS = ['avatars'];

    public function __construct(private FileStorage $storage)
    {
    }

    public function __invoke(string $key): Response
    {
        if (!self::isPublic($key)) {
            throw new NotFoundHttpException();
        }

        $file = $this->storage->read($key);

        if (null === $file) {
            throw new NotFoundHttpException();
        }

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->contentType,
            // La clave es única por fichero y una foto nueva estrena clave,
            // así que esto no puede quedarse viejo: cachear un año es seguro
            // y ahorra una petición por avatar y visita.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            // El fichero se sirve, nunca se interpreta.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private static function isPublic(string $key): bool
    {
        if (str_contains($key, '..') || str_ends_with($key, '.type')) {
            return false;
        }

        foreach (self::PUBLIC_FOLDERS as $folder) {
            if (str_starts_with($key, $folder.'/')) {
                return true;
            }
        }

        return false;
    }
}
