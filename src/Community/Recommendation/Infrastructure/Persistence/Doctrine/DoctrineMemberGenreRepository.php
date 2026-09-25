<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Recommendation\Domain\Entity\MemberGenre;
use LectoresBeta\Community\Recommendation\Domain\Repository\MemberGenreRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<MemberGenre>
 */
final class DoctrineMemberGenreRepository extends DoctrineRepository implements MemberGenreRepository
{
    public function replaceAllOf(MemberId $memberId, array $genres): void
    {
        $stored = [];

        foreach ($this->repository()->findBy(['memberId' => $memberId->value()]) as $row) {
            $stored[$row->genreCode()] = $row;
        }

        $wanted = [];

        foreach ($genres as $genre) {
            $wanted[$genre->genreCode()] = $genre;
        }

        // La diferencia, no borrar y volver a escribir: borrar e insertar la
        // misma fila en una transacción choca con la clave primaria, porque
        // Doctrine ordena las inserciones antes que las bajas.
        foreach ($stored as $code => $row) {
            if (!isset($wanted[$code])) {
                $this->forget($row);
            }
        }

        foreach ($wanted as $code => $genre) {
            if (!isset($stored[$code])) {
                $this->register($genre);
            }
        }
    }

    protected function entityClass(): string
    {
        return MemberGenre::class;
    }
}
