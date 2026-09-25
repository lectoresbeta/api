<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Domain\ValueObject;

/**
 * Un capítulo tal y como se le propone al autor (`FEAT-WRK-002`).
 *
 * Guarda los párrafos **sueltos y en texto plano**, no el HTML final. El
 * marcado se construye al confirmar, y pasa por el mismo saneador que el
 * editor (`FEAT-WRK-001`): un documento subido no merece más confianza que
 * un pegado desde Word, que es literalmente lo mismo con otro envoltorio.
 */
final readonly class ProposedChapter
{
    /**
     * @param list<string> $paragraphs
     */
    public function __construct(
        public ?string $title,
        public array $paragraphs,
    ) {
    }

    /**
     * El texto plano entero, que es lo que se cuenta y lo que se convierte en
     * párrafos al confirmar.
     */
    public function text(): string
    {
        return implode("\n", $this->paragraphs);
    }

    /**
     * Las primeras palabras, para que el autor reconozca el capítulo en la
     * pantalla de revisión sin que la respuesta lleve la novela entera.
     */
    public function preview(int $length): string
    {
        $flat = trim(preg_replace('/\s+/u', ' ', $this->text()) ?? '');

        if (mb_strlen($flat) <= $length) {
            return $flat;
        }

        // Sin el espacio que quede al cortar: «con …» se lee como un hueco y
        // «con…» como una frase que sigue.
        return rtrim(mb_substr($flat, 0, $length)).'…';
    }
}
