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
