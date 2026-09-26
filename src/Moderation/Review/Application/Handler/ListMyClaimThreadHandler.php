<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Handler;

use LectoresBeta\Moderation\Review\Application\DTO\ThreadMessage;
use LectoresBeta\Moderation\Review\Application\Query\ListMyClaimThread;
use LectoresBeta\Moderation\Review\Application\Service\ClaimThread;
use LectoresBeta\Moderation\Review\Domain\Entity\ClaimMessage;
use LectoresBeta\Moderation\Review\Domain\Enum\MessageAuthorType;
use LectoresBeta\Moderation\Review\Domain\Repository\ClaimMessageRepository;

/**
 * Mi hilo con moderación (`FEAT-MOD-009`).
 *
 * **Solo el mío.** Ni el de la otra parte ni la noticia de que exista: el
 * hilo se pide por la reclamación y quién soy, no por un identificador de
 * hilo que se pudiera cambiar a mano.
 *
 * Los mensajes salen **sin identidad de quien escribe** (`RN-4`): solo si
 * vinieron de moderación o de uno mismo. Protege al moderador de represalias
 * y es lo que permite discutir la decisión por su contenido.
 *
 * Un hilo vacío es una respuesta legítima: significa que moderación no ha
 * abierto conversación, no que algo haya fallado.
 */
final readonly class ListMyClaimThreadHandler
{
    public function __construct(
        private ClaimThread $threads,
        private ClaimMessageRepository $messages,
    ) {
    }

    /**
     * @return list<ThreadMessage>
     */
    public function __invoke(ListMyClaimThread $query): array
    {
        ['claim' => $claim, 'party' => $party] = $this->threads->of($query->claimId, $query->readerId);

        return array_map(
            static fn (ClaimMessage $message): ThreadMessage => new ThreadMessage(
                $message->id()->value(),
                MessageAuthorType::MODERATOR === $message->authorType(),
                $message->body(),
                $message->sentAt(),
            ),
            $this->messages->thread($claim->id(), $party),
        );
    }
}
