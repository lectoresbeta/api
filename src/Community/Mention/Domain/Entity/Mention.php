<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Domain\Entity;

use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;
use LectoresBeta\Community\Mention\Domain\ValueObject\MentionId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Alguien nombrado en una publicación o en un comentario (`FEAT-COM-032`).
 *
 * **Se guarda el `UserId`, nunca el texto**, y esa es la regla central de la
 * funcionalidad. Guardar «Juanjo Estévez» dejaría la mención mostrando para
 * siempre un nombre antiguo; guardar «@juanjoestevez» es peor, porque los
 * nombres de usuario se reciclan a los 30 días
 * ([`decision:0005`](../../../../../docs/decisions/0005-username-with-temporary-aliases.md))
 * y la mención acabaría atribuyendo palabras a quien no las dijo.
 *
 * El nombre se resuelve al mostrar, así que cambiarlo actualiza todas las
 * menciones pasadas a la vez.
 *
 * Mencionar **no concede acceso a nada** (`RN-3`): si la audiencia de lo
 * publicado excluye al mencionado, seguirá sin verlo.
 */
class Mention
{
    private string $id;

    private MentionSubject $subjectKind;

    private string $subjectId;

    private string $mentionedUserId;

    /** Dónde empieza en el texto, para que el cliente pinte el enlace. */
    private int $position;

    public function __construct(
        MentionId $id,
        MentionSubject $subjectKind,
        string $subjectId,
        MemberId $mentionedUserId,
        int $position,
    ) {
        $this->id = $id->value();
        $this->subjectKind = $subjectKind;
        $this->subjectId = $subjectId;
        $this->mentionedUserId = $mentionedUserId->value();
        $this->position = $position;
    }

    public function id(): MentionId
    {
        return MentionId::fromString($this->id);
    }

    public function subjectKind(): MentionSubject
    {
        return $this->subjectKind;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function mentionedUserId(): MemberId
    {
        return MemberId::fromString($this->mentionedUserId);
    }

    public function position(): int
    {
        return $this->position;
    }
}
