<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Application\Command\UpdateAuthorPageStyle;
use LectoresBeta\User\AuthorPage\Application\DTO\AuthorPageStyleView;
use LectoresBeta\User\AuthorPage\Application\Service\StoredAuthorPageStyle;
use LectoresBeta\User\AuthorPage\Domain\Enum\AccentColour;
use LectoresBeta\User\AuthorPage\Domain\Enum\AuthorPageTheme;
use LectoresBeta\User\AuthorPage\Domain\Repository\AuthorPageStyleRepository;

/**
 * Elegir tema y color (`FEAT-USR-016`).
 *
 * Los dos son **códigos de un catálogo cerrado**, y un valor desconocido se
 * rechaza nombrándolo (`RN-2`). Nada de CSS, nada de hexadecimales: lo que el
 * autor elige es un código, y lo que se sirve son estilos que escribió el
 * equipo.
 *
 * Un campo ausente no cambia nada: la pantalla tiene dos selectores y se
 * mueve uno cada vez, igual que en los ajustes de recepción.
 */
final readonly class UpdateAuthorPageStyleHandler
{
    public function __construct(
        private StoredAuthorPageStyle $stored,
        private AuthorPageStyleRepository $styles,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateAuthorPageStyle $command): AuthorPageStyleView
    {
        $style = $this->stored->of(UserId::fromString($command->userId));

        $style->change(
            $command->themeGiven ? AuthorPageTheme::orRefuse($command->theme) : $style->theme(),
            $command->accentGiven ? AccentColour::orRefuse($command->accentColour) : $style->accentColour(),
            $this->clock->now(),
        );

        $this->session->execute(function () use ($style): void {
            $this->styles->save($style);
        });

        return new AuthorPageStyleView($style->theme(), $style->accentColour(), $style->updatedAt());
    }
}
