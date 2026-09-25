<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\DTO;

/**
 * Por dónde se empieza: adónde mandar al navegador y con qué `state`.
 */
final readonly class OAuthStart
{
    public function __construct(
        public string $authorizationUrl,
        public string $state,
    ) {
    }
}
