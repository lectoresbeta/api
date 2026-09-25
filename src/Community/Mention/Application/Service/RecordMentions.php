<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Application\Service;

use LectoresBeta\Community\Mention\Application\DTO\MentionInput;
use LectoresBeta\Community\Mention\Domain\Entity\Mention;
use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;
use LectoresBeta\Community\Mention\Domain\Event\UserMentioned;
use LectoresBeta\Community\Mention\Domain\Exception\MentionRefused;
use LectoresBeta\Community\Mention\Domain\Repository\MentionRepository;
use LectoresBeta\Community\Mention\Domain\ValueObject\MentionId;
use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;

/**
 * Guardar las menciones de una publicación o de un comentario, y avisar a
 * quien corresponda (`FEAT-COM-032`).
 *
 * Tres reglas que conviene ver juntas porque se sostienen entre sí:
 *
 * - **se guarda el `UserId`, nunca el texto** (`RN-1`). Los nombres cambian y
 *   los nombres de usuario se reciclan a los 30 días, así que una mención
 *   guardada como cadena acabaría atribuyendo palabras a quien no las dijo;
 * - **el identificador lo manda el cliente y el servidor lo comprueba**
 *   (`RN-7`). Resolver la mención a partir del nombre escrito dejaría que
 *   cualquiera fabricara una que pareciera apuntar a otra persona;
 * - **no se avisa a quien no puede ver dónde se le menciona** (`RN-4`), y la
 *   comprobación se hace aquí y no en quien envía el aviso. Un aviso sobre
 *   algo que no se puede abrir contaría que existe, y la mención se
 *   convertiría en la vía para filtrar lo escrito para otros.
 */
final readonly class RecordMentions
{
    /**
     * Lo que cabe en una publicación o en un comentario.
     *
     * No sale de ninguna pantalla: sale de lo que pasa sin él. Una
     * publicación con doscientas menciones es un envío masivo de avisos que
     * cualquiera puede disparar (`I-11`).
     */
    public const MAX_PER_SUBJECT = 10;

    public function __construct(
        private MentionRepository $mentions,
        private RegisteredUsers $users,
        private AuthorSubscriptionRepository $subscriptions,
        private EventPublisher $events,
    ) {
    }

    /**
     * Comprueba y construye. **No publica nada**: los hechos salen después de
     * que la transacción cierre, con `announce()`.
     *
     * @param list<MentionInput> $wanted
     *
     * @return list<Mention>
     */
    public function of(MentionSubject $kind, string $subjectId, array $wanted, string $body): array
    {
        if (\count($wanted) > self::MAX_PER_SUBJECT) {
            throw MentionRefused::tooMany(self::MAX_PER_SUBJECT);
        }

        $length = mb_strlen($body);
        $mentions = [];
        $seen = [];

        foreach ($wanted as $mention) {
            try {
                $mentioned = MemberId::fromString($mention->userId);
            } catch (InvalidValue) {
                throw MentionRefused::unknownUser();
            }

            // Mencionar dos veces a la misma persona en el mismo texto es
            // normal al escribir; avisarle dos veces, no.
            if (isset($seen[$mentioned->value()])) {
                continue;
            }

            if (!$this->users->exists($mentioned->value())) {
                throw MentionRefused::unknownUser();
            }

            $seen[$mentioned->value()] = true;

            $mentions[] = new Mention(
                MentionId::generate(),
                $kind,
                $subjectId,
                $mentioned,
                max(0, min($mention->position, $length)),
            );
        }

        return $mentions;
    }

    /**
     * Los avisos, ya cerrada la transacción.
     *
     * @param list<Mention> $mentions
     */
    public function announce(array $mentions, Post $post, MemberId $by, \DateTimeImmutable $now): void
    {
        foreach ($mentions as $mention) {
            $mentioned = $mention->mentionedUserId();

            // Nadie se avisa a sí mismo (`RN-5`).
            if ($mentioned->value() === $by->value()) {
                continue;
            }

            if (!$this->canSee($post, $mentioned)) {
                continue;
            }

            $this->events->publish(new UserMentioned(
                EventId::generate(),
                $mentioned,
                $by,
                $post->id(),
                $mention->subjectKind(),
                $mention->subjectId(),
                $now,
            ));
        }
    }

    public function save(Mention $mention): void
    {
        $this->mentions->save($mention);
    }

    private function canSee(Post $post, MemberId $mentioned): bool
    {
        if ($post->authorId()->value() === $mentioned->value()) {
            return true;
        }

        if (PostAudience::EVERYONE === $post->audience()) {
            return true;
        }

        return null !== $this->subscriptions->between($mentioned, $post->authorId());
    }
}
