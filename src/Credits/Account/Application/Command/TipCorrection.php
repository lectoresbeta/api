<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Command;

final readonly class TipCorrection
{
    /**
     * `$idempotencyKey` distingue **reintentar** de **volver a propinar**.
     *
     * Sin él las dos peticiones son idénticas, y como una corrección solo
     * admite una propina (`RN-4`), la segunda se rechaza — que es lo correcto
     * para la segunda intención y lo incómodo para un reintento de red.
     */
    public function __construct(
        public string $correctionId,
        public string $authorId,
        public ?int $amount,
        public ?string $idempotencyKey = null,
    ) {
    }
}
