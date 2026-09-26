<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Handler;

use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\Exception\PostNotFound;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Application\Sharing\CanonicalUrl;
use LectoresBeta\Shared\Application\Sharing\ShareCard;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkCards;

/**
 * La tarjeta con la que se comparte una publicación fuera (`FEAT-COM-020`).
 *
 * **Solo las de audiencia `EVERYONE`.** Una publicación para seguidores no se
 * comparte: compartir no puede ser la puerta de atrás de la audiencia que su
 * autor eligió, y una tarjeta de previsualización es pública por definición —
 * la pide un rastreador sin sesión y acaba en la caché de una red social.
 *
 * Responde lo mismo para una que no existe, una eliminada y una restringida.
 * Distinguirlas contaría que hay una publicación ahí y que no es para ti, que
 * es más de lo que un extraño tiene que saber.
 *
 * **Si la publicación cita una obra, manda la obra.** Quien comparte «mirad
 * esto» con un relato dentro quiere que se vea el relato, no las dos primeras
 * líneas de su comentario.
 */
final readonly class GetPostShareCardHandler
{
    private const DESCRIPTION = 200;

    public function __construct(
        private PostRepository $posts,
        private WorkCards $works,
        private string $shareUrlTemplate,
        private string $siteName,
    ) {
    }

    public function __invoke(string $postId): ShareCard
    {
        try {
            $post = $this->posts->ofId(PostId::fromString($postId));
        } catch (InvalidValue) {
            throw PostNotFound::create();
        }

        if (null === $post || PostAudience::EVERYONE !== $post->audience()) {
            throw PostNotFound::create();
        }

        $url = CanonicalUrl::from($this->shareUrlTemplate, $post->id()->value());
        $work = null === $post->workId() ? null : ($this->works->ofWorks([$post->workId()->value()])[$post->workId()->value()] ?? null);

        if (null !== $work) {
            return new ShareCard($url, $work->title, self::preview($work->synopsis), null);
        }

        return new ShareCard(
            $url,
            $this->siteName,
            self::preview('' === $post->body() ? null : $post->body()),
            // La imagen se sirve por la publicación y no por el almacén, así
            // que la dirección es la misma que ve el muro.
            [] === $this->posts->attachmentsOf([$post->id()->value()])
                ? null
                : \sprintf('/api/v1/posts/%s/image', $post->id()->value()),
        );
    }

    private static function preview(?string $text): ?string
    {
        if (null === $text) {
            return null;
        }

        return mb_strlen($text) <= self::DESCRIPTION
            ? $text
            : rtrim(mb_substr($text, 0, self::DESCRIPTION)).'…';
    }
}
