<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * El texto de un capítulo, opcionalmente **en la versión que alguien leyó**
 * (`FEAT-FBK-004` `RN-8`).
 *
 * Es la única puerta por la que el contenido de una obra sale de `Work`, y
 * por eso conviene decir qué **no** hace: **no comprueba quién pregunta**.
 * Quién puede ver ese texto depende de quién corrigió ese capítulo, y eso lo
 * sabe `Feedback`, no este contexto. Un contrato que intentara autorizar
 * tendría que llamar a otro contrato mientras responde, que es justo lo que
 * [`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)
 * prohíbe.
 *
 * Quien llame a esto **tiene que haber autorizado antes**.
 */
interface ChapterTexts
{
    /**
     * @param int|null $version la versión que se quiere; `null` es la vigente
     */
    public function ofChapter(string $chapterId, ?int $version = null): ?ChapterText;
}
