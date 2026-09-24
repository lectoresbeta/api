<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Service;

use LectoresBeta\User\Profile\Domain\Exception\NotEnoughGenres;
use LectoresBeta\User\Profile\Domain\Exception\UnknownGenre;
use LectoresBeta\User\Profile\Domain\Repository\GenreRepository;

/**
 * Qué cuenta como una selección de géneros válida (`FEAT-USR-023` `RN-1`,
 * `RN-3`, `RN-4`; `FEAT-USR-009` `RN-2`, `RN-4`, `RN-5`, `RN-6`).
 *
 * **Vive en un solo sitio porque hay dos puertas al mismo dato**: el paso del
 * onboarding y la pantalla de Configuración. Duplicar las reglas es cómo se
 * acaba pudiendo quedarse con un género desde una pantalla y no desde la
 * otra, que es precisamente lo que un mínimo de tres pretende impedir.
 *
 * Está en Application y no en Domain porque necesita el catálogo, y el
 * catálogo es una tabla: no se puede responder «¿existe este género?» sin
 * preguntar fuera.
 */
final readonly class GenreSelection
{
    /**
     * Tres. Con menos, el filtro no dice nada de nadie; el número sale del
     * diseño del onboarding y se aplica igual al editar, porque un mínimo que
     * solo rige el primer día no es un mínimo.
     */
    public const MINIMUM = 3;

    public function __construct(private GenreRepository $genres)
    {
    }

    /**
     * La selección normalizada, o una excepción de negocio.
     *
     * `$kept` es lo que la persona ya tenía elegido, y solo sirve para una
     * cosa: **un género retirado que ya era suyo se conserva**
     * (`FEAT-USR-009` `RN-6`). Sin eso, retirar un género del catálogo haría
     * fallar el guardado de quien lo tuviera aunque estuviese editando otra
     * cosa — un campo que no se está tocando no puede tirar el formulario.
     *
     * Conservar no es elegir: un género retirado que no era suyo se rechaza
     * igual que uno inexistente.
     *
     * @param list<string> $codes
     * @param list<string> $kept
     *
     * @return list<string>
     */
    public function validated(array $codes, array $kept = []): array
    {
        $codes = self::normalise($codes);

        if (\count($codes) < self::MINIMUM) {
            throw NotEnoughGenres::atLeast(self::MINIMUM);
        }

        $unavailable = $this->genres->unknownAmong($codes);

        if ([] === $unavailable) {
            return $codes;
        }

        // De los que no están en el catálogo activo, los que sí existen como
        // fila son los retirados. Esos pasan si ya eran suyos.
        $retired = array_diff($unavailable, $this->genres->missingAmong($codes));
        $refused = array_values(array_diff($unavailable, array_intersect($retired, self::normalise($kept))));

        if ([] !== $refused) {
            throw UnknownGenre::among($refused);
        }

        return $codes;
    }

    /**
     * En mayúsculas y sin repetidos.
     *
     * Los duplicados se normalizan en vez de rechazarse: mandar dos veces el
     * mismo género es un fallo del cliente, no una decisión de la persona, y
     * el conjunto que quería decir no es ambiguo. Como consecuencia,
     * `['POETRY','poetry']` **no** llega al mínimo: es un género.
     *
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private static function normalise(array $codes): array
    {
        return array_values(array_unique(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            array_filter($codes, static fn (string $code): bool => '' !== trim($code)),
        )));
    }
}
