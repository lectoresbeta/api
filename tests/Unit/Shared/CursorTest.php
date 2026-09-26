<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Shared;

use LectoresBeta\Shared\Domain\Pagination\Cursor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La posición en un flujo cronológico
 * ([`paginación`](../../../docs/api/conventions/pagination.md)).
 *
 * Dos campos y no uno: ordenar solo por instante es ambiguo en cuanto dos
 * filas lo comparten, y «ambiguo» en un cursor significa una fila repetida o
 * perdida entre páginas.
 */
final class CursorTest extends TestCase
{
    public function testACursorSurvivesTheRoundTrip(): void
    {
        $at = new \DateTimeImmutable('2026-09-24 11:30:00');
        $cursor = Cursor::of($at, '0192f000-0000-7000-8000-000000000000');

        $decoded = Cursor::decode($cursor->encode());

        self::assertNotNull($decoded);
        self::assertSame($at->format(\DATE_ATOM), $decoded->at->format(\DATE_ATOM));
        self::assertSame('0192f000-0000-7000-8000-000000000000', $decoded->id);
    }

    /**
     * Opaco a propósito: un cursor que se lea como una fecha invita a que un
     * cliente construya el suyo, y entonces el orden de la consulta no puede
     * volver a cambiar nunca.
     */
    public function testTheCursorDoesNotAdvertiseWhatItCarries(): void
    {
        $encoded = Cursor::of(new \DateTimeImmutable('2026-09-24 11:30:00'), 'abc')->encode();

        self::assertStringNotContainsString('2026', $encoded);
        self::assertStringNotContainsString('abc', $encoded);
        self::assertSame($encoded, rawurlencode($encoded), 'Y viaja en una URL sin escaparse.');
    }

    /**
     * El tercer campo, para los flujos que ordenan por algo calculado.
     *
     * Allí el instante ya es solo el desempate, así que la posición tiene que
     * llevar la puntuación o la página siguiente empieza donde la aritmética
     * caiga esta vez.
     */
    public function testARankedCursorCarriesItsScore(): void
    {
        $at = new \DateTimeImmutable('2026-09-24 11:30:00');

        $decoded = Cursor::decode(Cursor::ranked(7, $at, 'abc')->encode());

        self::assertNotNull($decoded);
        self::assertSame(7, $decoded->rank);
        self::assertSame($at->format(\DATE_ATOM), $decoded->at->format(\DATE_ATOM));
        self::assertSame('abc', $decoded->id);
    }

    /**
     * Y uno sin puntuación se sigue leyendo como lo que es: los dos formatos
     * conviven, porque la mayoría de los listados ordenan por fecha.
     */
    public function testAPlainCursorHasNoRank(): void
    {
        $decoded = Cursor::decode(Cursor::of(new \DateTimeImmutable('2026-09-24 11:30:00'), 'abc')->encode());

        self::assertNotNull($decoded);
        self::assertNull($decoded->rank);
    }

    /**
     * Se distinguen por la forma y no por una marca, así que conviene fijar
     * el caso que podría confundirlos: un identificador que empieza por un
     * número no convierte un cursor plano en uno puntuado.
     */
    public function testAnIdentifierThatLooksLikeANumberDoesNotBecomeARank(): void
    {
        $at = new \DateTimeImmutable('2026-09-24 11:30:00');

        $decoded = Cursor::decode(Cursor::of($at, '42')->encode());

        self::assertNotNull($decoded);
        self::assertNull($decoded->rank, 'La fecha va primero: no hay dónde confundirse.');
        self::assertSame('42', $decoded->id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rubbish(): iterable
    {
        yield 'texto suelto' => ['lo-que-sea'];
        yield 'base64 sin separador' => [base64_encode('solo-una-cosa')];
        yield 'fecha ilegible' => [base64_encode('ayer|abc')];
        yield 'sin identificador' => [base64_encode('2026-09-24T11:30:00+00:00|')];
        yield 'vacío' => [''];
    }

    /**
     * Nada de esto es «sin cursor», y la diferencia importa: servir la
     * primera página a quien mandó uno roto le enseña otros resultados sin
     * forma de notarlo. Quien pregunta decide, y en esta API eso es un `422`.
     */
    #[DataProvider('rubbish')]
    public function testWhatThisApiDidNotIssueIsNotACursor(string $encoded): void
    {
        self::assertNull(Cursor::decode($encoded));
    }
}
