<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\ValueObject;

use LectoresBeta\Community\Post\Domain\Enum\PostType;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Por qué se filtra el muro (`FEAT-COM-009`).
 *
 * **Se filtra por intención, no por formato.** `docs/ui/home.md` dejaba la
 * duda abierta —una publicación tiene las dos dimensiones— y la respuesta
 * está en para qué sirve cada una: la intención dice *qué quiere* quien
 * publica, que es lo que alguien busca cuando filtra; el formato dice si
 * llevaba una foto, que es cómo se pinta. Nadie entra al muro a buscar
 * publicaciones con imagen.
 *
 * Todos los campos son opcionales y se combinan con **Y**: cada uno estrecha
 * lo anterior. Es lo que espera cualquiera que haya usado un buscador, y la
 * alternativa —mezclar con O— haría que añadir un filtro devolviera más
 * resultados.
 *
 * Es un objeto y no seis parámetros más en el repositorio: un método con
 * once argumentos posicionales se llama mal una vez y ya nadie lo nota.
 *
 * Vive en `Domain` y no en `Application` porque lo recibe un contrato de
 * repositorio, que es de `Domain`: al revés, la capa de dentro dependería de
 * la de fuera.
 */
final readonly class PostFilters
{
    private function __construct(
        public ?PostType $type,
        public ?string $text,
        /**
         * Quién **escribió** la publicación.
         *
         * No es lo mismo que el muro de una persona (`FEAT-COM-026`): allí un
         * repost cuenta como de quien lo saca, aquí como de quien lo escribió.
         * Son dos preguntas distintas —«¿qué hay en su muro?» y «¿qué ha
         * escrito?»— y se parecen lo justo para confundirlas si compartieran
         * campo.
         */
        public ?string $authorId,
        public ?\DateTimeImmutable $from,
        public ?\DateTimeImmutable $to,
    ) {
    }

    public static function none(): self
    {
        return new self(null, null, null, null, null);
    }

    /**
     * Lo que llega de la petición, ya validado.
     *
     * Una fecha mal escrita **se rechaza** en vez de ignorarse: quien filtra
     * por fechas está acotando, y devolverle el muro entero porque escribió
     * mal un día le haría creer lo contrario de lo que ve.
     */
    public static function of(
        ?string $type,
        ?string $text,
        ?string $authorId,
        ?string $from,
        ?string $to,
    ): self {
        $filters = new self(
            null === $type || '' === $type
                ? null
                : PostType::tryFrom(strtoupper($type)) ?? throw InvalidValue::because('That is not a kind of post.'),
            null === $text || '' === trim($text) ? null : trim($text),
            null === $authorId || '' === $authorId ? null : $authorId,
            self::moment($from, 'from'),
            self::moment($to, 'to'),
        );

        if (null !== $filters->from && null !== $filters->to && $filters->from > $filters->to) {
            throw InvalidValue::because('The range ends before it starts.');
        }

        return $filters;
    }

    public function areEmpty(): bool
    {
        return null === $this->type
            && null === $this->text
            && null === $this->authorId
            && null === $this->from
            && null === $this->to;
    }

    private static function moment(?string $value, string $field): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw InvalidValue::because(\sprintf('«%s» is not a date.', $field));
        }
    }
}
