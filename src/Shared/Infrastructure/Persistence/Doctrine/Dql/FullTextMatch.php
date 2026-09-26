<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\Dql;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * `FULLTEXT_MATCH(campo, :texto)` en DQL, que es
 * `to_tsvector('spanish', campo) @@ plainto_tsquery('spanish', ?)` en
 * PostgreSQL (`FEAT-COM-009`).
 *
 * Existe porque DQL no sabe de búsqueda de texto y la alternativa era
 * reescribir en SQL nativo una consulta —la del muro— cuyas reglas de
 * audiencia, bloqueo y cursor son lo más delicado de este backend. Una
 * función de DQL deja esas reglas donde están y añade una condición más.
 *
 * **El idioma va aquí, escrito, y no en configuración**, aunque tenga toda la
 * pinta de un parámetro. La razón es que el índice que hace esto viable se
 * crea en una migración con ese mismo idioma dentro: si los dos dejaran de
 * coincidir, PostgreSQL no usaría el índice y la búsqueda pasaría de
 * instantánea a un escaneo completo **sin dar ningún error**. Cambiarlo es
 * una migración que reconstruye el índice, no una variable de entorno.
 *
 * `plainto_tsquery` y no `to_tsquery`: lo que llega es lo que alguien escribió
 * en una caja de búsqueda, no una expresión. `to_tsquery` reventaría con un
 * apóstrofo o un paréntesis, que es la mitad de lo que la gente escribe.
 */
final class FullTextMatch extends FunctionNode
{
    /**
     * Cambiarlo obliga a reconstruir `idx_post_search`. Ver arriba.
     */
    private const CONFIGURATION = 'spanish';

    private ?Node $field = null;

    private ?Node $text = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->field = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->text = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        if (null === $this->field || null === $this->text) {
            throw new \LogicException('FULLTEXT_MATCH was not parsed.');
        }

        return \sprintf(
            "(to_tsvector('%s', %s) @@ plainto_tsquery('%s', %s))",
            self::CONFIGURATION,
            $this->field->dispatch($sqlWalker),
            self::CONFIGURATION,
            $this->text->dispatch($sqlWalker),
        );
    }
}
