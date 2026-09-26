<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Handler;

use LectoresBeta\Community\Mention\Application\Service\RecordMentions;
use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;
use LectoresBeta\Community\Post\Application\Command\CreatePost;
use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Entity\PostAttachment;
use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\Enum\PostFormat;
use LectoresBeta\Community\Post\Domain\Enum\PostType;
use LectoresBeta\Community\Post\Domain\Event\PostPublished;
use LectoresBeta\Community\Post\Domain\Exception\PostNotFound;
use LectoresBeta\Community\Post\Domain\Exception\PostRefused;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\Service\PostImagePolicy;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostAttachmentId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostBody;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostLink;
use LectoresBeta\Community\Post\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Image\ImageProcessor;
use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Application\Storage\StoredFile;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Preferences\Application\Contract\ProposalRecipients;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Publicar en el muro (`FEAT-COM-002`).
 *
 * Tres decisiones viven aquí y conviene verlas juntas:
 *
 * - **el formato se deriva**, no se acepta (`RN-12`). Una publicación con
 *   imagen es `IMAGE`, con enlace `LINK`, con relato `WORK`, y sin nada
 *   `TEXT`. Pedirle al cliente que lo declare es pedirle que repita algo que
 *   ya está diciendo, y abrir la puerta a que lo diga mal;
 * - **un adjunto como máximo** (`RN-1`), y dos se rechazan en vez de quedarse
 *   con el primero: quien manda dos cree que va a publicar dos;
 * - **la imagen se reescribe siempre**, como el avatar. Un reencodificado no
 *   puede arrastrar metadatos, y los de una foto llevan dónde se tomó.
 *
 * **Buscar lectores beta exige una obra** (`FEAT-COM-003`), tuya y publicada.
 * Es la única intención que impone un adjunto, y por lo que significa: las
 * otras tres describen lo que alguien quiere; esta pide algo concreto sobre
 * un texto concreto, y sin él nadie puede atenderla.
 *
 * **Y las dos que piden que te aborden exigen tener la puerta abierta**
 * (`FEAT-COM-004`, `FEAT-COM-005`). Publicar «busco writing buddy» con las
 * propuestas cerradas en los ajustes manda contra un muro a todo el que
 * responda, y quien publicó no se entera nunca de por qué no le escribe
 * nadie.
 */
final readonly class CreatePostHandler
{
    public function __construct(
        private PostRepository $posts,
        private RecordMentions $mentions,
        private WorkAccessBriefs $works,
        private ProposalRecipients $reception,
        private ImageProcessor $images,
        private FileStorage $storage,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreatePost $command): string
    {
        $authorId = MemberId::fromString($command->authorId);
        $body = PostBody::fromString($command->body);
        $link = PostLink::fromString($command->linkUrl);
        $type = self::type($command->type);

        $this->doorHasToBeOpen($type, $command->authorId);

        $work = $this->work($command->workId, $command->authorId, $type);
        $image = null === $command->image || '' === $command->image ? null : $command->image;

        $attachments = array_filter([$image, $link, $work], static fn (mixed $one): bool => null !== $one);

        if (\count($attachments) > 1) {
            throw PostRefused::tooManyAttachments();
        }

        if (null === $body && [] === $attachments) {
            throw PostRefused::empty();
        }

        $postId = PostId::generate();
        $now = $this->clock->now();

        $post = new Post(
            $postId,
            $authorId,
            $body?->value() ?? '',
            $type,
            self::format($image, $link, $work),
            self::audience($command->audience),
            $now,
        );

        if (null !== $link) {
            $post->linkTo($link->value());
        }

        if (null !== $work) {
            $post->promoteWork($work);
        }

        // Guardar el fichero antes de abrir la transacción: subirlo es lento y
        // ajeno, y una transacción abierta mientras se habla con el almacén es
        // exactamente lo que `AGENTS.md` pide no hacer. Si la transacción no
        // llega a cerrarse queda un fichero huérfano, que es el lado barato
        // del error — el caro sería una fila apuntando a un fichero que no
        // está.
        $key = null === $image ? null : $this->store($image);

        // Se comprueban antes de abrir la transacción: una mención a quien no
        // existe rechaza la publicación entera, y descubrirlo a medias de
        // escribir sería descubrirlo tarde.
        $mentions = $this->mentions->of(
            MentionSubject::POST,
            $postId->value(),
            $command->mentions,
            $post->body(),
        );

        $this->session->execute(function () use ($post, $postId, $key, $mentions): void {
            $this->posts->save($post);

            if (null !== $key) {
                $this->posts->attach(new PostAttachment(
                    PostAttachmentId::generate(),
                    $postId,
                    $key['url'],
                    $key['mediaType'],
                ));
            }

            foreach ($mentions as $mention) {
                $this->mentions->save($mention);
            }
        });

        $this->events->publish(new PostPublished(
            EventId::generate(),
            $postId,
            $authorId,
            $post->type(),
            $post->format(),
            $post->audience(),
            $now,
        ));

        $this->mentions->announce($mentions, $post, $authorId, $now);

        return $postId->value();
    }

    /**
     * No se pide en el muro lo que se tiene cerrado en los ajustes
     * (`FEAT-COM-004` `RN-2`, `FEAT-COM-005` `RN-2`).
     *
     * Se comprueba **al publicar y solo al publicar**: cerrar la recepción
     * después no retira la publicación. Una es un acto con fecha y la otra un
     * ajuste que cambia cuando quiera su dueño, y hacer desaparecer textos
     * viejos al tocar un interruptor sorprendería a cualquiera.
     *
     * Las otras dos intenciones no comprueban nada. `GENERAL` no pide que te
     * aborden, y `LOOKING_FOR_BETA_READERS` pide que **te lean**, que es una
     * solicitud de acceso y no una propuesta: tiene sus propias reglas en
     * `FEAT-COM-003` y ninguna de estas dos puertas la gobierna.
     */
    private function doorHasToBeOpen(PostType $type, string $authorId): void
    {
        $door = match ($type) {
            PostType::LOOKING_FOR_WRITING_BUDDY => 'writingBuddyProposals',
            PostType::OFFERING_AS_BETA_READER => 'betaReaderInvitations',
            default => null,
        };

        if (null === $door) {
            return;
        }

        if (true !== ($this->reception->openDoorsOf($authorId)[$door] ?? false)) {
            throw PostRefused::whileTheDoorIsShut($door);
        }
    }

    /**
     * El relato que se promociona, si lo hay.
     *
     * Tiene que **existir y ser visible para quien publica** (`RN-14`). Se
     * pregunta por contrato publicado: si una obra existe para alguien es de
     * `Work` y de nadie más, y rehacer esa regla aquí la dejaría escrita dos
     * veces.
     */
    private function work(?string $workId, string $authorId, PostType $type): ?WorkId
    {
        $recruiting = PostType::LOOKING_FOR_BETA_READERS === $type;

        if (null === $workId || '' === $workId) {
            // «Busco lectores» sin decir para qué es una petición que nadie
            // puede atender (`FEAT-COM-003` `RN-1`).
            return $recruiting ? throw PostRefused::withoutAWorkToRead() : null;
        }

        $brief = $this->works->ofWork($workId);

        if (null === $brief || (!$brief->visibleToOthers && $brief->authorId !== $authorId)) {
            throw PostNotFound::work();
        }

        if ($recruiting) {
            // Reclutar para lo que escribió otro sería decidir por él a quién
            // enseña su texto (`RN-2`), y anunciar un borrador mandaría a
            // quien responda a una puerta cerrada (`RN-3`).
            if ($brief->authorId !== $authorId) {
                throw PostRefused::forSomebodyElsesWork();
            }

            if (!$brief->visibleToOthers) {
                throw PostRefused::forAWorkNobodyCanSeeYet();
            }
        }

        return WorkId::fromString($workId);
    }

    /**
     * @return array{url: string, mediaType: string}
     */
    private function store(string $bytes): array
    {
        // El tamaño se mide sobre lo que llegó y no sobre lo que se guarda: el
        // límite existe para no tragarse un fichero enorme, y a lo guardado ya
        // lo acota el redimensionado.
        if (\strlen($bytes) > PostImagePolicy::MAX_BYTES) {
            throw PostRefused::imageTooLarge();
        }

        $processed = $this->images->normalise($bytes, PostImagePolicy::MAX_SIDE)
            ?? throw PostRefused::unsupportedImage();

        $key = \sprintf('posts/%s.%s', bin2hex(random_bytes(16)), $processed->extension);
        $this->storage->put($key, new StoredFile($processed->contents, $processed->contentType));

        return ['url' => $key, 'mediaType' => $processed->contentType];
    }

    private static function type(?string $type): PostType
    {
        return null === $type || '' === $type
            ? PostType::GENERAL
            : PostType::tryFrom(strtoupper($type)) ?? throw InvalidValue::because('That is not a kind of post.');
    }

    /**
     * Sin audiencia, **la más abierta**, que es lo que enseña el modal por
     * defecto. Aquí sí se elige el valor permisivo a conciencia y no por
     * descuido: publicar es un acto de enseñar, y quien quiere restringir lo
     * dice.
     */
    private static function audience(?string $audience): PostAudience
    {
        return null === $audience || '' === $audience
            ? PostAudience::EVERYONE
            : PostAudience::tryFrom(strtoupper($audience)) ?? throw InvalidValue::because('That is not an audience.');
    }

    private static function format(?string $image, ?PostLink $link, ?WorkId $work): PostFormat
    {
        return match (true) {
            null !== $image => PostFormat::IMAGE,
            null !== $link => PostFormat::LINK,
            null !== $work => PostFormat::WORK,
            default => PostFormat::TEXT,
        };
    }
}
