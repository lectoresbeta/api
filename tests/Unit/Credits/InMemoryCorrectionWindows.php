<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionWindow;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionWindowRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

/**
 * Vacío por defecto, que es «no se sabe nada de ninguna obra» — y eso no
 * bloquea. Así las pruebas que no hablan de la puerta no tienen que abrirla.
 */
final class InMemoryCorrectionWindows implements CorrectionWindowRepository
{
    /** @var array<string, CorrectionWindow> */
    private array $windows = [];

    public function save(CorrectionWindow $window): void
    {
        $this->windows[$window->workId()->value()] = $window;
    }

    public function open(string $workId, \DateTimeImmutable $at): void
    {
        $window = CorrectionWindow::closedAtBirth(WorkId::fromString($workId), $at);
        $window->applyVersion(true, 1, $at);
        $this->windows[$workId] = $window;
    }

    public function ofWork(WorkId $workId): ?CorrectionWindow
    {
        return $this->windows[$workId->value()] ?? null;
    }

    public function stateOf(array $workIds): array
    {
        $state = [];

        foreach ($workIds as $workId) {
            if (isset($this->windows[$workId])) {
                $state[$workId] = $this->windows[$workId]->isOpen();
            }
        }

        return $state;
    }
}
