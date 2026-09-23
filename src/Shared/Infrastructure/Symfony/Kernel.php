<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Symfony;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The kernel lives in Infrastructure, not at the root of src/.
 *
 * This is not cosmetic: AGENTS.md requires Symfony to remain an
 * infrastructure detail, and leaving the kernel in src/Kernel.php would place
 * it above the bounded contexts, as if the framework were the centre of the
 * system.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 4);
    }
}
