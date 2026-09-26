<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\User;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * El nombre de usuario (`FEAT-USR-033`).
 *
 * Nadie lo elige al registrarse: **se fabrica a partir del correo**, y esa es
 * la parte delicada. Un correo admite casi cualquier cosa y un nombre de
 * usuario no, así que la conversión tiene que producir siempre algo válido —
 * o alguien se queda sin poder registrarse por llevar un punto en su
 * dirección.
 */
final class UsernameTest extends TestCase
{
    public function testItIsCaseInsensitive(): void
    {
        self::assertTrue(Username::fromString('Pablo')->equals(Username::fromString('pablo')));
        self::assertSame('pablo', Username::fromString('  Pablo  ')->value());
    }

    #[DataProvider('unacceptable')]
    public function testTheFormatIsNarrowOnPurpose(string $value): void
    {
        $this->expectException(InvalidValue::class);

        Username::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unacceptable(): iterable
    {
        yield 'demasiado corto' => ['ab'];
        yield 'demasiado largo' => [str_repeat('a', 31)];
        yield 'con guion' => ['con-guion'];
        yield 'con punto' => ['con.punto'];
        yield 'con espacio' => ['con espacio'];
        yield 'con acento' => ['pequeña'];
        yield 'vacío' => ['   '];
    }

    /**
     * Lo que sobra del correo se retira en vez de rechazarse: quien escribe
     * `pablo.blanco+beta@…` tiene una dirección perfectamente válida y no
     * puede quedarse sin cuenta por ella.
     */
    #[DataProvider('addresses')]
    public function testALocalPartAlwaysProducesAValidName(string $localPart, string $expected): void
    {
        self::assertSame($expected, Username::candidateFrom($localPart)->value());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'tal cual' => ['pabloblanco', 'pabloblanco'];
        yield 'mayúsculas' => ['PabloBlanco', 'pabloblanco'];
        yield 'puntos y etiqueta' => ['pablo.blanco+beta', 'pabloblancobeta'];
        yield 'guiones' => ['pablo-blanco', 'pabloblanco'];
        yield 'demasiado largo' => [str_repeat('a', 40), str_repeat('a', 30)];
    }

    /**
     * Un local corto, o uno que al limpiarlo no deja nada, sigue teniendo que
     * producir un nombre de al menos tres caracteres.
     */
    public function testAShortOrUnusableLocalPartStillProducesAName(): void
    {
        self::assertSame('jo0', Username::candidateFrom('jo')->value());
        self::assertSame('usuario', Username::candidateFrom('...')->value());
    }

    /**
     * El sufijo es lo que resuelve la colisión, y **cabe siempre**: el
     * nombre base se recorta para dejarle sitio en vez de pasarse del máximo.
     */
    public function testTheSuffixAlwaysFits(): void
    {
        self::assertSame('pabloblanco_1', Username::candidateFrom('pabloblanco', 1)->value());

        $largo = Username::candidateFrom(str_repeat('a', 40), 12);

        self::assertSame(str_repeat('a', 27).'_12', $largo->value());
        self::assertSame(30, mb_strlen($largo->value()));
    }
}
