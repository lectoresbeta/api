<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Pagination;

/**
 * A position in an ordered stream, as an opaque string.
 *
 * Two fields at least, and not one: ordering by an instant alone is ambiguous
 * the moment two rows share it, and «ambiguous» in a cursor means an item
 * repeated or skipped between pages. The identifier breaks the tie, and it is
 * the reason every query that takes a cursor must order by both.
 *
 * A third, optional, for the streams ordered by something computed rather
 * than by time — the relevance of a comment, for instance. There the instant
 * is already the tie-breaker, so the position needs the score itself or the
 * next page starts wherever the arithmetic happens to land.
 *
 * Opaque on purpose. A cursor that reads as a date invites a client to build
 * one, and then the ordering of the query can never change again.
 */
final readonly class Cursor
{
    private function __construct(
        public \DateTimeImmutable $at,
        public string $id,
        public ?int $rank = null,
    ) {
    }

    public static function of(\DateTimeImmutable $at, string $id): self
    {
        return new self($at, $id);
    }

    /**
     * A position in a stream ordered by a computed score, with the instant
     * and the identifier breaking its ties.
     */
    public static function ranked(int $rank, \DateTimeImmutable $at, string $id): self
    {
        return new self($at, $id, $rank);
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

        $ranked = self::ranking($raw);

        if (null !== $ranked) {
            return $ranked;
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
        $body = null === $this->rank
            ? $this->at->format(\DATE_ATOM).'|'.$this->id
            : $this->rank.'|'.$this->at->format(\DATE_ATOM).'|'.$this->id;

        return rtrim(strtr(base64_encode($body), '+/', '-_'), '=');
    }

    /**
     * Se distingue por la forma y no por una marca: un rango es un entero, y
     * una fecha `DATE_ATOM` nunca lo es. Marcar el tipo habría sido un campo
     * más que puede mentir.
     */
    private static function ranking(string $raw): ?self
    {
        $parts = explode('|', $raw, 3);

        if (3 !== \count($parts) || '' === $parts[2] || 1 !== preg_match('/^-?\d+$/', $parts[0])) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $parts[1]);

        return false === $parsed ? null : new self($parsed, $parts[2], (int) $parts[0]);
    }
}
