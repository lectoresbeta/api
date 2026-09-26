<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Sharing;

/**
 * Lo que pinta WhatsApp, Twitter o LinkedIn cuando alguien pega un enlace de
 * la plataforma (`FEAT-WRK-011`, `FEAT-COM-020`).
 *
 * **El enlace no se genera: es la dirección canónica de siempre.** No lleva
 * token, no caduca y no abre nada que no fuera ya público. Compartir no puede
 * ser una puerta trasera a lo que la audiencia cierra, y un enlace con
 * credencial dentro circulando por redes sociales es exactamente eso.
 *
 * **No lleva a nadie dentro**, y no es un olvido. Una tarjeta la pide un
 * rastreador anónimo, y poner ahí el nombre de quien escribe obligaría a
 * resolver su privacidad de perfil para alguien que no tiene sesión — que es
 * justo la clase de cosa por la que se filtra un dato. La tarjeta habla del
 * contenido, no de la persona.
 */
final readonly class ShareCard
{
    public function __construct(
        public string $url,
        public string $title,
        public ?string $description,
        public ?string $imageUrl,
    ) {
    }
}
