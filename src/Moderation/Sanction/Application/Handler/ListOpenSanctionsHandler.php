<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Application\Handler;

use LectoresBeta\Moderation\Sanction\Application\DTO\OpenSanction;
use LectoresBeta\Moderation\Sanction\Application\DTO\OpenSanctionQueue;
use LectoresBeta\Moderation\Sanction\Application\Query\ListOpenSanctions;
use LectoresBeta\Moderation\Sanction\Domain\Entity\Sanction;
use LectoresBeta\Moderation\Sanction\Domain\Repository\SanctionRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;

/**
 * La cola de asuntos vivos del backoffice (`FEAT-MOD-006` `MOD-25`).
 *
 * **Una suspensión total es indefinida por diseño, y eso la hace
 * cualitativamente distinta**: nadie la levanta si nadie se acuerda. Sin esta
 * pantalla se convierte en una expulsión silenciosa que ningún moderador
 * decidió — el castigo más grave del catálogo, aplicado por olvido.
 *
 * De la más antigua a la más reciente, y con **cuántos días lleva abierta**,
 * porque es el dato por el que se entra aquí. Las de plazo fijo no aparecen:
 * terminan por su fecha y no hay nada que recordar.
 *
 * No es una cola de trabajo con estado propio: es una **consulta** sobre las
 * sanciones que ya existen. Marcar una como «revisada» sería inventar un
 * estado que la ficha no pide, y que además envejecería igual de mal.
 */
final readonly class ListOpenSanctionsHandler
{
    private const MAX_PER_PAGE = 50;

    public function __construct(
        private SanctionRepository $sanctions,
        private Clock $clock,
    ) {
    }

    public function __invoke(ListOpenSanctions $query): OpenSanctionQueue
    {
        $page = max(1, $query->page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $query->perPage));
        $now = $this->clock->now();

        return new OpenSanctionQueue(
            array_map(
                static fn (Sanction $sanction): OpenSanction => new OpenSanction(
                    $sanction->id()->value(),
                    $sanction->userId()->value(),
                    $sanction->type()->value,
                    $sanction->reason(),
                    $sanction->imposedAt()->format(\DATE_ATOM),
                    (int) $now->diff($sanction->imposedAt())->days,
                ),
                $this->sanctions->openIndefinitely($perPage, ($page - 1) * $perPage),
            ),
            $this->sanctions->countOpenIndefinitely(),
            $page,
            $perPage,
        );
    }
}
