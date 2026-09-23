<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Symfony;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * El kernel vive en Infrastructure, no en la raíz de src/.
 *
 * No es cosmética: AGENTS.md exige que Symfony siga siendo un detalle de
 * infraestructura, y dejar el kernel en src/Kernel.php lo colocaría por
 * encima de los bounded contexts, como si el framework fuese el centro del
 * sistema.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 4);
    }
}
