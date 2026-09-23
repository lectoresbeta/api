<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Entity;

/**
 * One entry of the genre catalogue (`FEAT-USR-023`).
 *
 * A table and not a PHP enum because the specification asks for order and an
 * active flag: the list is meant to be curated without a deploy, and the
 * client reads it from the API instead of hard-coding it (`RN-5`).
 *
 * The identity is the code, which is what travels in payloads and in other
 * contexts. A surrogate key would buy nothing and would make every reference
 * unreadable.
 */
class Genre
{
    private string $code;

    private string $name;

    private int $position;

    private bool $active = true;

    public function __construct(string $code, string $name, int $position)
    {
        $this->code = strtoupper($code);
        $this->name = $name;
        $this->position = $position;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    /**
     * Retiring a genre never deletes it: works and people already point at
     * the code, and those references have to keep resolving.
     */
    public function retire(): void
    {
        $this->active = false;
    }

    public function restore(): void
    {
        $this->active = true;
    }
}
