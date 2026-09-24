<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Pagination;

/**
 * A position in a chronological stream, as an opaque string.
 *
 * Two fields and not one: ordering by an instant alone is ambiguous the
 * moment two rows share it, and «ambiguous» in a cursor means an item
 * repeated or skipped between pages. The identifier breaks the tie, and it is
 * the reason every query that takes a cursor must order by both.
 *
 * Opaque on purpose. A cursor that reads as a date invites a client to build
 * one, and then the ordering of the query can never change again.
 */
final readonly class Cursor
{
    private function __construct(
        public \DateTimeImmutable $at,
        public string $id,
    ) {
    }

    public static function of(\DateTimeImmutable $at, string $id): self
    {
        return new self($at, $id);
    }

    /**
     * `null` for anything that is not a cursor this class produced.
     *
     * Whoever asks decides what that means. It is deliberately **not** the
     * same as «no cursor»: silently serving the first page to somebody who
     * sent a broken one shows them different results with no way to notice.
     */
    public static function decode(string $encoded): ?self
    {
        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);

        if (false === $raw || !str_contains($raw, '|')) {
            return null;
        }

        [$at, $id] = explode('|', $raw, 2);
        $parsed = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $at);

        if (false === $parsed || '' === $id) {
            return null;
        }

        return new self($parsed, $id);
    }

    public function encode(): string
    {
        return rtrim(strtr(base64_encode($this->at->format(\DATE_ATOM).'|'.$this->id), '+/', '-_'), '=');
    }
}
