<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\ContentPreference;
use LectoresBeta\User\Preferences\Domain\Enum\ContentWarning;
use LectoresBeta\User\Preferences\Domain\Repository\ContentPreferencesRepository;

/**
 * @extends DoctrineRepository<ContentPreference>
 */
final class DoctrineContentPreferencesRepository extends DoctrineRepository implements ContentPreferencesRepository
{
    public function excludedBy(UserId $userId): array
    {
        return array_map(
            static fn (ContentPreference $row): ContentWarning => $row->warning(),
            $this->repository()->findBy(['userId' => $userId->value()], ['warning' => 'ASC']),
        );
    }

    /**
     * Se escribe la diferencia, no se borra y se vuelve a escribir entero.
     *
     * Dos razones: borrar e insertar la misma fila en la misma transacción
     * choca con la clave primaria —Doctrine ordena las inserciones antes que
     * las bajas—, y además conserva **desde cuándo** excluye cada etiqueta
     * quien no ha tocado esa, que es lo que la fecha dice.
     */
    public function replace(UserId $userId, array $warnings, \DateTimeImmutable $now): void
    {
        $stored = [];

        foreach ($this->repository()->findBy(['userId' => $userId->value()]) as $row) {
            $stored[$row->warning()->value] = $row;
        }

        $wanted = [];

        foreach ($warnings as $warning) {
            $wanted[$warning->value] = $warning;
        }

        foreach ($stored as $code => $row) {
            if (!isset($wanted[$code])) {
                $this->forget($row);
            }
        }

        foreach ($wanted as $code => $warning) {
            if (!isset($stored[$code])) {
                $this->register(new ContentPreference($userId, $warning, $now));
            }
        }
    }

    protected function entityClass(): string
    {
        return ContentPreference::class;
    }
}
