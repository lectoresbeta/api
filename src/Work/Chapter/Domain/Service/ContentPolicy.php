<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Service;

/**
 * What a chapter is allowed to contain (`FEAT-WRK-001` `RN-8`).
 *
 * The list is a **business rule**, not a configuration detail, so it lives in
 * Domain even though the parsing that enforces it is infrastructure.
 *
 * What is missing from it is the interesting part. **No links and no
 * images**: literature does not need them, and leaving them out removes the
 * most obvious spam vector and about half the complexity of sanitising in one
 * go. Almost nobody will miss them, and it saves trouble for years. No
 * tables, no inline styles, no `span`, no `div` either.
 */
final class ContentPolicy
{
    /**
     * @var list<string>
     */
    public const ALLOWED_TAGS = [
        // Paragraphs and line breaks.
        'p', 'br',
        // Bold and italics.
        'strong', 'em',
        // Quotations.
        'blockquote',
        // Subheadings inside a chapter.
        'h2', 'h3',
        // Scene separator.
        'hr',
    ];

    /**
     * Markup that is thrown away **while its text is kept**.
     *
     * This list is the difference between welcoming an author and losing
     * their novel. A paste from Word arrives wrapped in `span`s and `div`s,
     * and the sanitiser's default for anything it does not recognise is to
     * drop the element **with its children** — which would delete the whole
     * chapter and report success.
     *
     * So everything here is unwrapped instead: `<b>hola</b>` loses its bold
     * and keeps «hola». What is **not** here — `script`, `style`, `iframe`,
     * `object`, `embed` — is dropped whole, contents included, because their
     * contents are not prose.
     *
     * @var list<string>
     */
    public const UNWRAPPED_TAGS = [
        // The usual wrappers of a paste from a word processor.
        'div', 'span', 'section', 'article', 'main', 'header', 'footer', 'font',
        // Formatting we do not keep, but whose words we do.
        'b', 'i', 'u', 's', 'small', 'mark', 'sub', 'sup', 'code', 'pre',
        // Links and their text. The text stays; the destination does not.
        'a',
        // Headings outside the two we allow.
        'h1', 'h4', 'h5', 'h6',
        // Lists and tables: the prose survives, the structure does not.
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];
}
