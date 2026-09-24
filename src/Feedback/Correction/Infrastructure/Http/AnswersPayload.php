<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Las respuestas tal y como llegan por HTTP, convertidas en algo con forma.
 *
 * Lo comparten guardar y enviar porque es literalmente el mismo cuerpo: lo
 * que cambia entre las dos operaciones es qué se exige de él, y eso se decide
 * en el dominio, no aquí.
 *
 * Llega como **lista** y sale como mapa por pregunta. La lista conserva el
 * orden en el JSON, que es lo natural de escribir para un cliente; el mapa es
 * lo que evita tener que decidir qué hacer con dos respuestas a la misma
 * pregunta — la última gana, y es la que el lector ve en pantalla.
 */
final readonly class AnswersPayload
{
    /**
     * @return array<string, string>
     */
    public static function of(Request $request): array
    {
        $raw = json_decode((string) $request->getContent(), true);

        if (!\is_array($raw) || !\is_array($raw['answers'] ?? null)) {
            throw new BadRequestHttpException('The body must carry a list of answers.');
        }

        $answers = [];

        foreach ($raw['answers'] as $item) {
            if (!\is_array($item) || !\is_string($item['questionId'] ?? null) || !\is_string($item['text'] ?? null)) {
                throw new BadRequestHttpException('Every answer must carry a questionId and its text.');
            }

            $answers[$item['questionId']] = $item['text'];
        }

        return $answers;
    }
}
