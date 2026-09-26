<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Content;

use LectoresBeta\Work\Chapter\Application\Port\ContentSanitiser;
use LectoresBeta\Work\Chapter\Domain\Service\ContentPolicy;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterContent;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The port, over Symfony's HTML sanitiser.
 *
 * A library and not a hand-written filter on purpose: sanitising HTML is a
 * problem with a long history of clever bypasses, and the sensible amount of
 * it to invent here is none.
 *
 * The configuration is built **from `ContentPolicy`**, so the allowed list has
 * one home: adding a tag is a change to the rule in Domain, not to this
 * adapter.
 */
final readonly class WhitelistContentSanitiser implements ContentSanitiser
{
    private HtmlSanitizer $sanitiser;

    public function __construct()
    {
        $config = new HtmlSanitizerConfig();

        foreach (ContentPolicy::ALLOWED_TAGS as $tag) {
            // No attribute is ever allowed. Without this, `class` and friends
            // would ride along on tags that are otherwise harmless.
            $config = $config->allowElement($tag, []);
        }

        foreach (ContentPolicy::UNWRAPPED_TAGS as $tag) {
            // `block` removes the tag and **keeps its children**. Leaving
            // these to the default would drop them whole: a paste from Word
            // is all `span`s, and the chapter would arrive empty while the
            // request reported success.
            $config = $config->blockElement($tag);
        }

        // A chapter is tens of thousands of words, well past the default cap.
        $this->sanitiser = new HtmlSanitizer($config->withMaxInputLength(-1));
    }

    public function sanitise(string $html): ChapterContent
    {
        $clean = $this->sanitiser->sanitize($html);

        return new ChapterContent($clean, self::plainTextOf($clean));
    }

    /**
     * The plain text, derived **from the sanitised HTML** and not from the
     * input: what gets counted has to be what gets stored.
     *
     * Block-level tags become a space so that `<p>uno</p><p>dos</p>` counts as
     * two words and not as «unodos».
     */
    private static function plainTextOf(string $html): string
    {
        $spaced = (string) preg_replace('#<(br|/p|/h2|/h3|/blockquote|hr)\b[^>]*>#i', ' ', $html);
        $plain = html_entity_decode(strip_tags($spaced), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }
}
