<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reading the JSON body of a request, once and safely.
 *
 * This is transport-level parsing and it stops here: what crosses into
 * Application is a command with typed fields, never an array and never the
 * `Request` (`AGENTS.md`).
 *
 * Absent and wrongly-typed are the same answer — `null` — on purpose. A
 * controller has to handle both identically anyway, and distinguishing them
 * only invites a second code path that nobody tests.
 */
final readonly class JsonBody
{
    /**
     * @param array<array-key, mixed> $values
     */
    private function __construct(private array $values)
    {
    }

    public static function of(Request $request): self
    {
        $raw = $request->getContent();

        if ('' === trim($raw)) {
            return new self([]);
        }

        try {
            $decoded = json_decode($raw, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('The request body is not valid JSON.');
        }

        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }

        return new self($decoded);
    }

    public function string(string $field): ?string
    {
        $value = $this->values[$field] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * A field that is itself an object, such as `acceptedLegalVersions`.
     */
    public function nested(string $field): self
    {
        $value = $this->values[$field] ?? null;

        return new self(\is_array($value) ? $value : []);
    }
}
