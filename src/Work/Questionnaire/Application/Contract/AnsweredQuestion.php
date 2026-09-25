<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\Contract;

/**
 * Una pregunta del cuestionario **tal y como estaba** en una versión
 * concreta.
 *
 * Datos planos, nunca la entidad
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)):
 * quien recibe un agregado acaba navegándolo, y el modelo interno vuelve a
 * estar compartido.
 */
final readonly class AnsweredQuestion
{
    public function __construct(
        public string $questionId,
        public int $position,
        public string $statement,
    ) {
    }
}
