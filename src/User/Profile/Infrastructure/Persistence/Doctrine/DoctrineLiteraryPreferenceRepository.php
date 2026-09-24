<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Domain\Entity\LiteraryPreference;
use LectoresBeta\User\Profile\Domain\Repository\LiteraryPreferenceRepository;

/**
 * @extends DoctrineRepository<LiteraryPreference>
 */
final class DoctrineLiteraryPreferenceRepository extends DoctrineRepository implements LiteraryPreferenceRepository
{
    public function codesOf(UserId $userId): array
    {
        return array_map(
            static fn (LiteraryPreference $preference): string => $preference->genreCode(),
            $this->rowsOf($userId),
        );
    }

    /**
     * **Solo la diferencia**, y no borrar todo y volver a insertar.
     *
     * La razón es una restricción real de Doctrine, no una optimización: la
     * identidad de una fila es `(user_id, genre_code)`, y borrar y registrar
     * la misma identidad en la misma transacción hace que la unidad de
     * trabajo se encuentre dos objetos para el mismo identificador y falle.
     * Quien guarda conservando un género que ya tenía —el caso normal: se
     * cambia uno y se dejan los demás— se llevaría un error.
     *
     * De paso, `selectedAt` de lo que se conserva no se toca, que es lo
     * cierto: ese género se eligió cuando se eligió.
     */
    public function replaceAllOf(UserId $userId, array $codes, \DateTimeImmutable $now): void
    {
        $wanted = array_values(array_unique(array_map(strtoupper(...), $codes)));
        $current = $this->codesOf($userId);

        foreach ($this->rowsOf($userId) as $preference) {
            if (!\in_array($preference->genreCode(), $wanted, true)) {
                $this->forget($preference);
            }
        }

        foreach (array_diff($wanted, $current) as $code) {
            $this->register(new LiteraryPreference($userId, $code, $now));
        }
    }

    protected function entityClass(): string
    {
        return LiteraryPreference::class;
    }

    /**
     * @return list<LiteraryPreference>
     */
    private function rowsOf(UserId $userId): array
    {
        return array_values($this->repository()->findBy(['userId' => $userId->value()]));
    }
}
