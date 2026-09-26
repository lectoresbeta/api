<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Application\Image\ImageProcessor;
use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Application\Storage\StoredFile;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\AuthorPage\Application\Command\UpdatePublishedBookCover;
use LectoresBeta\User\AuthorPage\Application\DTO\PublishedBookView;
use LectoresBeta\User\AuthorPage\Application\Service\MyPublishedBook;
use LectoresBeta\User\AuthorPage\Domain\Exception\PublishedBookRefused;
use LectoresBeta\User\AuthorPage\Domain\Repository\PublishedBookRepository;
use LectoresBeta\User\AuthorPage\Domain\Service\PublishedBookPolicy;

/**
 * La portada de una obra publicada (`FEAT-USR-029`).
 *
 * **La imagen se reescribe siempre**, como cualquier otra que suba alguien
 * (`file-uploads.md`): decodificar y volver a codificar es lo único que
 * garantiza que no queden metadatos, y los metadatos de una foto llevan
 * **dónde se tomó**. Que una portada de libro parezca un dato inocuo no
 * cambia el fichero que llega.
 *
 * **No se recorta a proporción de libro.** Las portadas reales no miden todas
 * lo mismo, y recortar la de alguien para que encaje en una cuadrícula es
 * estropearla; la proporción 2:3 es una recomendación de diseño, no una regla
 * que se le pueda imponer a un libro que ya existe.
 *
 * Y se borra lo que sustituye: una portada reemplazada deja de ser accesible
 * en vez de quedarse huérfana en el almacén para siempre.
 */
final readonly class UpdatePublishedBookCoverHandler
{
    /**
     * Carpeta propia, y pública: una portada aparece en un perfil que se abre
     * sin sesión. Lo que decide qué se sirve en abierto es el prefijo, así
     * que separar por carpeta es separar por permiso.
     */
    private const FOLDER = 'book-covers';

    public function __construct(
        private MyPublishedBook $mine,
        private PublishedBookRepository $books,
        private ImageProcessor $images,
        private FileStorage $storage,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(UpdatePublishedBookCover $command): PublishedBookView
    {
        $book = $this->mine->of($command->userId, $command->publishedBookId);

        if (null === $command->image || '' === $command->image) {
            throw PublishedBookRefused::missingCover();
        }

        // El tamaño se mide sobre lo que llegó y no sobre lo que se guarda:
        // el límite existe para no tragarse un fichero enorme, y a lo
        // guardado ya lo acota el redimensionado.
        if (\strlen($command->image) > PublishedBookPolicy::COVER_MAX_BYTES) {
            throw PublishedBookRefused::coverTooLarge();
        }

        $cover = $this->images->normalise($command->image, PublishedBookPolicy::COVER_MAX_SIDE)
            ?? throw self::refuse($command->image);

        $replaced = $book->coverUrl();
        $key = \sprintf('%s/%s.%s', self::FOLDER, bin2hex(random_bytes(16)), $cover->extension);

        $this->storage->put($key, new StoredFile($cover->contents, $cover->contentType));

        $this->session->execute(function () use ($book, $key): void {
            $book->setCover($key);
            $this->books->save($book);
        });

        if (null !== $replaced && $replaced !== $key) {
            $this->storage->delete($replaced);
        }

        return PublishedBookView::of($book);
    }

    /**
     * Distingue «no es una imagen» de «es una imagen que no se puede leer»,
     * que llevan a cosas distintas: elegir otro fichero, o volver a
     * exportarlo. Un HEIC de iPhone cae en el primero (`F-3`).
     */
    private static function refuse(string $bytes): PublishedBookRefused
    {
        return false === @getimagesizefromstring($bytes)
            ? PublishedBookRefused::unsupportedCoverType()
            : PublishedBookRefused::unreadableCover();
    }
}
