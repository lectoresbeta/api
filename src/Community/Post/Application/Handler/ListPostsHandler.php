<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Handler;

use LectoresBeta\Community\Interaction\Domain\Entity\PostRepost;
use LectoresBeta\Community\Interaction\Domain\Repository\PostRepostRepository;
use LectoresBeta\Community\Mention\Application\Service\ResolveMentions;
use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;
use LectoresBeta\Community\Post\Application\DTO\PostCard;
use LectoresBeta\Community\Post\Application\DTO\PostPage;
use LectoresBeta\Community\Post\Application\Query\ListPosts;
use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use LectoresBeta\User\Account\Application\Contract\VisibleProfiles;

/**
 * El muro (`FEAT-COM-001`), con lo publicado y lo reposteado.
 *
 * **El filtro de audiencia va en la consulta** (`RN-2`), y en las dos: la de
 * publicaciones y la de reposts. Traer lo que no se puede ver para
 * descartarlo después rompería la paginación —una página de veinte
 * devolvería doce sin motivo— y dejaría el texto de alguien viajando por
 * dentro del servidor.
 *
 * **Un repost se filtra por la audiencia del original** (`FEAT-COM-019`
 * `RN-5`), no por la de quien repostea, porque no la amplía. Es la regla más
 * fácil de romper de las dos funcionalidades: basta con servir los reposts
 * sin volver a mirar el original para publicar contenido restringido.
 *
 * Las dos listas se mezclan aquí y no en la base de datos. Cada una llega
 * ordenada y con una fila de más, así que los primeros `n` de la mezcla son
 * los primeros `n` de verdad; una unión en SQL sería más rápida y mucho menos
 * legible por una diferencia que este muro no va a notar.
 *
 * El perfil ajeno es un **techo**, igual que en las listas de seguidores:
 * quien no es visible para quien mira no aparece, y su publicación tampoco.
 * Un muro no es una lista de textos, es una lista de personas diciendo cosas.
 */
final readonly class ListPostsHandler
{
    public function __construct(
        private PostRepository $posts,
        private PostRepostRepository $reposts,
        private AuthorSubscriptionRepository $subscriptions,
        private UserBlockRepository $blocks,
        private VisibleProfiles $profiles,
        private ResolveMentions $mentions,
    ) {
    }

    public function __invoke(ListPosts $query): PostPage
    {
        $reader = MemberId::fromString($query->readerId);
        $limit = PageSize::of($query->limit);
        $after = null === $query->cursor || '' === $query->cursor
            ? null
            : Cursor::decode($query->cursor) ?? throw InvalidCursor::create();

        $followed = $this->subscriptions->followedBy($reader);
        $hidden = $this->blocks->involving($reader);

        $entries = [];

        foreach ($this->posts->wallFor($reader, $followed, $hidden, $after, $limit) as $post) {
            $entries[] = ['post' => $post, 'repost' => null, 'at' => $post->createdAt(), 'id' => $post->id()->value()];
        }

        $reposts = $this->reposts->wallFor($reader, $followed, $hidden, $after, $limit);
        $reposted = $this->posts->ofIds(array_map(
            static fn (PostRepost $repost): string => $repost->postId()->value(),
            $reposts,
        ));

        foreach ($reposts as $repost) {
            $original = $reposted[$repost->postId()->value()] ?? null;

            if (null === $original) {
                continue;
            }

            $entries[] = [
                'post' => $original,
                'repost' => $repost,
                'at' => $repost->createdAt(),
                'id' => $repost->id()->value(),
            ];
        }

        usort($entries, static function (array $one, array $other): int {
            return [$other['at'], $other['id']] <=> [$one['at'], $one['id']];
        });

        $entries = self::withoutRepeats($entries);
        $rows = \array_slice($entries, 0, $limit);
        $last = end($rows);

        return new PostPage($this->cards($rows, $query->readerId), \count($entries) > $limit && false !== $last
            ? Cursor::of($last['at'], $last['id'])->encode()
            : null);
    }

    /**
     * Una publicación **una vez por página**, aunque llegue por dos caminos.
     *
     * Pasa en cuanto alguien repostea algo que ya veías: lo publicado es
     * público, así que estaba en tu muro por sí mismo, y el repost lo trae
     * otra vez. Ver la misma tarjeta dos veces seguidas parece un error de la
     * plataforma, así que se queda **la entrada más reciente** —normalmente
     * el repost, que es el hecho nuevo— y se descarta la otra.
     *
     * Es por página y no global a propósito: recordar entre páginas lo que ya
     * se enseñó exigiría un cursor que llevara esa lista dentro.
     *
     * @param list<array{post: Post, repost: ?PostRepost, at: \DateTimeImmutable, id: string}> $entries
     *
     * @return list<array{post: Post, repost: ?PostRepost, at: \DateTimeImmutable, id: string}>
     */
    private static function withoutRepeats(array $entries): array
    {
        $seen = [];
        $kept = [];

        foreach ($entries as $entry) {
            $postId = $entry['post']->id()->value();

            if (isset($seen[$postId])) {
                continue;
            }

            $seen[$postId] = true;
            $kept[] = $entry;
        }

        return $kept;
    }

    /**
     * @param list<array{post: Post, repost: ?PostRepost, at: \DateTimeImmutable, id: string}> $rows
     *
     * @return list<PostCard>
     */
    private function cards(array $rows, string $readerId): array
    {
        $people = [];

        foreach ($rows as $row) {
            $people[$row['post']->authorId()->value()] = true;

            if (null !== $row['repost']) {
                $people[$row['repost']->memberId()->value()] = true;
            }
        }

        $profiles = $this->profiles->visibleTo($readerId, array_keys($people));

        $postIds = array_values(array_unique(array_map(
            static fn (array $row): string => $row['post']->id()->value(),
            $rows,
        )));

        $attachments = $this->posts->attachmentsOf($postIds);
        $mentions = $this->mentions->of(MentionSubject::POST, $postIds);

        $cards = [];

        foreach ($rows as $row) {
            $post = $row['post'];
            $author = $profiles[$post->authorId()->value()] ?? null;

            if (null === $author) {
                continue;
            }

            $repost = $row['repost'];
            $repostedBy = null === $repost ? null : ($profiles[$repost->memberId()->value()] ?? null);

            // Quien repostea también es un techo: si ha dejado de ser visible
            // para quien mira, su repost no tiene cabecera que pintar.
            if (null !== $repost && !$repostedBy instanceof DirectoryEntry) {
                continue;
            }

            $cards[] = new PostCard(
                $post->id()->value(),
                $author,
                $post->body(),
                $post->type()->value,
                $post->format()->value,
                $post->audience()->value,
                // La dirección de la imagen apunta a la publicación, no al
                // almacén: una publicación para seguidores no puede quedar
                // protegida solo por lo difícil que es adivinar una clave.
                isset($attachments[$post->id()->value()])
                    ? \sprintf('/api/v1/posts/%s/image', $post->id()->value())
                    : null,
                $post->linkUrl(),
                $post->workId()?->value(),
                $post->commentCount(),
                $post->likeCount(),
                $post->repostCount(),
                $post->wasEdited(),
                $post->createdAt(),
                $repostedBy,
                $repost?->comment(),
                $repost?->createdAt(),
                $mentions[$post->id()->value()] ?? [],
            );
        }

        return $cards;
    }
}
