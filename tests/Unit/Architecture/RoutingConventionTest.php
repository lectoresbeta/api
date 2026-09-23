<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Las rutas se declaran en YAML, un fichero por bounded context
 * ([`decision:0010`](../../../docs/decisions/0010-routes-declared-in-yaml-per-context.md)).
 *
 * Una convención que solo vive en un documento se incumple el día que alguien
 * tiene prisa, y nadie se entera hasta la revisión —si la hay—. Esto la
 * comprueba en cada ejecución, igual que Deptrac comprueba las capas.
 *
 * Lee ficheros, no arranca el kernel: sigue siendo un test de milisegundos.
 */
final class RoutingConventionTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    /**
     * Lo que la convención prohíbe. Un `#[Route]` en un controlador reparte
     * el mapa de la API entre setenta ficheros y el YAML deja de ser la
     * respuesta a «¿qué expone este contexto?».
     */
    public function testNoControllerDeclaresItsOwnRoutes(): void
    {
        $offenders = [];

        foreach ($this->phpFilesUnderSrc() as $file) {
            $source = (string) file_get_contents($file->getPathname());

            if (1 === preg_match('/#\[\s*Route\b/', $source)
                || str_contains($source, 'Symfony\Component\Routing\Attribute\Route')
            ) {
                $offenders[] = $this->relative($file->getPathname());
            }
        }

        self::assertSame([], $offenders, implode("\n", [
            'Las rutas no se declaran con atributos. Van en config/routes/<contexto>.yaml.',
            'Ver docs/api/conventions/routing.md.',
        ]));
    }

    /**
     * `config/routes.yaml` es el punto de entrada de Symfony y tiene que
     * quedarse vacío: una ruta ahí no pertenecería a ningún contexto.
     */
    public function testTheEntryPointDeclaresNoRoutes(): void
    {
        self::assertNull(
            Yaml::parseFile(self::ROOT.'/config/routes.yaml'),
            'config/routes.yaml declara rutas. Deben ir en config/routes/<contexto>.yaml.',
        );
    }

    /**
     * Un fichero por contexto, aunque esté vacío: `ls config/routes/` es el
     * mapa de qué expone API y qué no.
     */
    public function testEveryBoundedContextHasItsOwnRouteFile(): void
    {
        foreach ($this->boundedContexts() as $context) {
            $file = \sprintf('%s/config/routes/%s.yaml', self::ROOT, strtolower($context));

            self::assertFileExists($file, \sprintf(
                'El contexto %s no tiene fichero de rutas. Créalo aunque esté vacío.',
                $context,
            ));
        }
    }

    public function testEveryDeclaredRoutePointsAtAMethodThatExists(): void
    {
        foreach ($this->declaredRoutes() as $name => $definition) {
            self::assertArrayHasKey('controller', $definition, \sprintf('La ruta %s no declara controlador.', $name));

            [$class, $method] = explode('::', (string) $definition['controller']) + [1 => '__invoke'];

            self::assertTrue(class_exists($class), \sprintf('La ruta %s apunta a una clase que no existe: %s', $name, $class));
            self::assertTrue(method_exists($class, $method), \sprintf('La ruta %s apunta a un método que no existe: %s::%s', $name, $class, $method));
        }
    }

    /**
     * El nombre de cada ruta es su `operationId` en OpenAPI.
     *
     * Es lo que convierte «la implementación y la especificación no deben
     * divergir» de buen propósito en algo comprobable: una ruta que no
     * aparece en el contrato publicado es un endpoint que existe y que nadie
     * documentó.
     */
    public function testEveryRouteNameIsAnOperationIdInOpenApi(): void
    {
        $operations = $this->operationIds();

        foreach (array_keys($this->declaredRoutes()) as $name) {
            self::assertContains((string) $name, $operations, \sprintf(
                'La ruta %s no tiene operationId en openapi/. Toda ruta implementada se documenta.',
                $name,
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function boundedContexts(): array
    {
        $contexts = [];

        foreach ((array) scandir(self::ROOT.'/src') as $entry) {
            if (\is_string($entry) && !str_starts_with($entry, '.') && is_dir(self::ROOT.'/src/'.$entry)) {
                $contexts[] = $entry;
            }
        }

        return $contexts;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function declaredRoutes(): array
    {
        $routes = [];

        foreach ((array) glob(self::ROOT.'/config/routes/*.yaml') as $file) {
            /** @var array<string, array<string, mixed>>|null $parsed */
            $parsed = Yaml::parseFile((string) $file);

            foreach ($parsed ?? [] as $name => $definition) {
                $routes[$name] = $definition;
            }
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function operationIds(): array
    {
        $ids = [];

        foreach ((array) glob(self::ROOT.'/openapi/{,*/}*.yaml', \GLOB_BRACE) as $file) {
            $source = (string) file_get_contents((string) $file);

            if (preg_match_all('/^\s*operationId:\s*(\S+)/m', $source, $matches)) {
                $ids = [...$ids, ...$matches[1]];
            }
        }

        return $ids;
    }

    /**
     * @return iterable<\SplFileInfo>
     */
    private function phpFilesUnderSrc(): iterable
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::ROOT.'/src', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && 'php' === $file->getExtension()) {
                yield $file;
            }
        }
    }

    private function relative(string $path): string
    {
        $root = realpath(self::ROOT);
        $real = realpath($path);

        if (!\is_string($root) || !\is_string($real)) {
            return $path;
        }

        return str_replace($root.'/', '', $real);
    }
}
