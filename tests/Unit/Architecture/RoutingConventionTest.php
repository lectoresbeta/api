<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Las rutas se declaran en YAML, un fichero por bounded context y **dentro
 * del propio contexto**
 * ([`decision:0010`](../../../docs/decisions/0010-routes-declared-in-yaml-per-context.md),
 * ubicación fijada por
 * [`decision:0011`](../../../docs/decisions/0011-route-files-live-inside-their-context.md)).
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
            'Las rutas no se declaran con atributos. Van en src/<Contexto>/Infrastructure/routes.yaml.',
            'Ver docs/api/conventions/routing.md.',
        ]));
    }

    /**
     * `config/routes.yaml` es solo el índice: importa, no declara. Una ruta
     * ahí no pertenecería a ningún contexto.
     */
    public function testTheEntryPointOnlyImports(): void
    {
        foreach ($this->entryPoint() as $name => $entry) {
            self::assertArrayHasKey('resource', $entry, \sprintf(
                'config/routes.yaml declara la ruta %s. Debe ir en el fichero de su contexto.',
                $name,
            ));
            self::assertArrayNotHasKey('path', $entry, \sprintf(
                'config/routes.yaml declara un `path` en %s. Solo importa.',
                $name,
            ));
        }
    }

    /**
     * Un fichero por contexto, aunque esté vacío: quien añada el primer
     * endpoint no tiene que averiguar dónde va.
     */
    public function testEveryBoundedContextHasItsOwnRouteFile(): void
    {
        foreach ($this->boundedContexts() as $context) {
            self::assertFileExists($this->routeFileOf($context), \sprintf(
                'El contexto %s no tiene fichero de rutas. Créalo aunque esté vacío.',
                $context,
            ));
        }
    }

    /**
     * El modo de fallo que este test existe para evitar: un fichero de rutas
     * que está ahí, con su contenido, y que **nadie importa**. Los endpoints
     * simplemente no existen, sin ningún error, y se descubre en producción.
     *
     * Es el precio de no usar un glob en `config/routes.yaml`, y se paga
     * aquí.
     */
    public function testEveryContextRouteFileIsImported(): void
    {
        $imported = array_map(
            static fn (array $entry): string => basename(\dirname((string) $entry['resource'], 2)),
            array_filter($this->entryPoint(), static fn (array $entry): bool => isset($entry['resource'])),
        );

        foreach ($this->boundedContexts() as $context) {
            self::assertContains($context, $imported, \sprintf(
                'El fichero de rutas de %s existe pero config/routes.yaml no lo importa: sus endpoints no existirían.',
                $context,
            ));
        }
    }

    /**
     * `src/<Contexto>/Infrastructure/` es el único sitio del árbol donde una
     * carpeta de capa vive al nivel del concepto de negocio, y está ahí para
     * un fichero de configuración. **No es una capa donde poner código.**.
     *
     * Si lo fuera, el segundo nivel dejaría de ser el concepto —que es la
     * regla que sostiene toda la estructura— y nadie sabría si
     * `src/Work/Infrastructure/` es configuración o una capa más.
     *
     * `Shared` queda fuera: no es un bounded context y sí tiene sus tres
     * capas ahí.
     */
    public function testTheContextInfrastructureFolderHoldsNothingButItsRoutes(): void
    {
        foreach ($this->boundedContexts() as $context) {
            if ('Shared' === $context) {
                continue;
            }

            $entries = array_values(array_diff(
                (array) scandir(\dirname($this->routeFileOf($context))),
                ['.', '..'],
            ));

            self::assertSame(['routes.yaml'], $entries, \sprintf(
                'src/%s/Infrastructure/ solo puede contener routes.yaml. El código va en su concepto.',
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

    private function routeFileOf(string $context): string
    {
        return \sprintf('%s/src/%s/Infrastructure/routes.yaml', self::ROOT, $context);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function entryPoint(): array
    {
        /** @var array<string, array<string, mixed>>|null $parsed */
        $parsed = Yaml::parseFile(self::ROOT.'/config/routes.yaml');

        return $parsed ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function declaredRoutes(): array
    {
        $routes = [];

        foreach ((array) glob(self::ROOT.'/src/*/Infrastructure/routes.yaml') as $file) {
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
