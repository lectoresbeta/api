<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Image;

/**
 * Una imagen ya normalizada: reescrita por el servidor, sin metadatos y con
 * un formato conocido.
 */
final readonly class ProcessedImage
{
    public function __construct(
        public string $contents,
        public string $contentType,
        public string $extension,
        public int $width,
        public int $height,
    ) {
    }
}
