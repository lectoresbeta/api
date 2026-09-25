<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Application\Service;

use LectoresBeta\Community\Mention\Application\DTO\MentionView;
use LectoresBeta\Community\Mention\Domain\Entity\Mention;
use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;
use LectoresBeta\Community\Mention\Domain\Repository\MentionRepository;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;

/**
 * Las menciones de una página, con el **nombre de ahora** (`FEAT-COM-032`
 * `RN-2`).
 *
 * Aquí es donde se paga lo que se decidió al guardarlas: como en la base de
 * datos hay identificadores y no texto, cambiar de nombre actualiza todas las
 * menciones pasadas a la vez, y cambiar de `@usuario` no mueve ninguna de
 * sitio.
 *
 * Se pregunta por `ProfileCards` y no por `VisibleProfiles`, que es el
 * hermano cuidadoso, y la razón es la misma que en la lista de bloqueados:
 * **quien pregunta ya sabe quién es** —lo está leyendo en un texto que tiene
 * delante— y filtrar por privacidad no escondería la mención, solo la dejaría
 * sin nombre. Lo que sí hace la privacidad es no dar el enlace, que es lo que
 * la tarjeta decide con el `userId`.
 *
 * Una cuenta eliminada no vuelve, y su mención se pinta **de forma neutra**
 * (`RN-6`): sin nombre y sin enlace, en vez de fallar o llevar a ninguna
 * parte.
 */
final readonly class ResolveMentions
{
    public function __construct(
        private MentionRepository $mentions,
        private ProfileCards $profiles,
    ) {
    }

    /**
     * @param list<string> $subjectIds
     *
     * @return array<string, list<MentionView>> indexado por sujeto
     */
    public function of(MentionSubject $kind, array $subjectIds): array
    {
        $bySubject = $this->mentions->of($kind, $subjectIds);

        if ([] === $bySubject) {
            return [];
        }

        $mentioned = [];

        foreach ($bySubject as $mentions) {
            foreach ($mentions as $mention) {
                $mentioned[$mention->mentionedUserId()->value()] = true;
            }
        }

        $cards = $this->profiles->of(array_keys($mentioned));

        $views = [];

        foreach ($bySubject as $subjectId => $mentions) {
            $views[$subjectId] = array_map(
                static function (Mention $mention) use ($cards): MentionView {
                    $card = $cards[$mention->mentionedUserId()->value()] ?? null;

                    return new MentionView(
                        $card?->userId,
                        $card?->name,
                        $mention->position(),
                    );
                },
                $mentions,
            );
        }

        return $views;
    }
}
