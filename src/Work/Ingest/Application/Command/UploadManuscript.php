<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Application\Command;

final readonly class UploadManuscript
{
    public function __construct(
        public string $authorId,
        public string $bytes,
        public string $filename,
    ) {
    }
}
