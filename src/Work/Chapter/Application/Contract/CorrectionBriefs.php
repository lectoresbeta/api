<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * `Work`'s published contract for the context that collects corrections
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **It asks, it never commands.** Nothing here opens a chapter, grants
 * anybody access or changes a questionnaire; it hands over data and the
 * caller decides what to do with it.
 *
 * Why synchronous and not an event: the questions are **the author's text**,
 * and `QuestionnaireUpdated` deliberately leaves them out — a queue that
 * persists and retries is no place for a manuscript's content. A reader who
 * opens the panel needs them now, which is exactly the case this kind of
 * contract exists for.
 */
interface CorrectionBriefs
{
    public function ofChapter(string $chapterId): ?CorrectionBrief;
}
