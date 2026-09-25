<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Service;

use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\Exception\PostNotFound;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Una publicación, **si quien pregunta puede verla** (`FEAT-COM-001` `RN-2`).
 *
 * Fíjate en cómo está preguntado: no es «dame esta publicación» —que
 * obligaría a quien llama a acordarse de comprobar la audiencia— sino «dame
 * la que esta persona puede ver». La diferencia es la que separa una regla
 * que se cumple siempre de una que se cumple mientras nadie la olvide.
 *
 * Existe porque la misma pregunta aparece en cuatro sitios: abrir su imagen,
 * comentarla, responder a un comentario suyo y repostearla. Escrita cuatro
 * veces, sería el sitio donde falta la cuarta.
 *
 * El muro **no la usa**: allí el filtro va dentro de la consulta, porque
 * comprobar veinte publicaciones una a una después de traerlas rompería la
 * paginación. Son la misma regla escrita para dos formas distintas de
 * preguntar, y por eso viven pegadas: si una cambia, la otra también.
 */
final readonly class VisiblePost
{
    public function __construct(
        private PostRepository $posts,
        private AuthorSubscriptionRepository $subscriptions,
        private UserBlockRepository $blocks,
    ) {
    }

    public function to(string $postId, string $readerId): Post
    {
        try {
            $post = $this->posts->ofId(PostId::fromString($postId));
            $reader = MemberId::fromString($readerId);
        } catch (InvalidValue) {
            throw PostNotFound::create();
        }

        if (null === $post) {
            throw PostNotFound::create();
        }

        $author = $post->authorId();

        if ($author->value() === $reader->value()) {
            return $post;
        }

        // El bloqueo antes que la audiencia: es bidireccional en el efecto, y
        // vale incluso sobre lo que se publicó para cualquiera.
        if ($this->blocks->existsBetween($reader, $author)) {
            throw PostNotFound::create();
        }

        if (PostAudience::EVERYONE === $post->audience()) {
            return $post;
        }

        if (null === $this->subscriptions->between($reader, $author)) {
            throw PostNotFound::create();
        }

        return $post;
    }
}
