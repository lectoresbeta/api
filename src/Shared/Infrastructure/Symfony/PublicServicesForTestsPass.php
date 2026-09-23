<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Symfony;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Makes this project's own services public, in the test environment only.
 *
 * Symfony removes private services that nothing injects, and an integration
 * test asking for one then gets «has been removed or inlined». Most of the
 * repositories are in exactly that state: they exist and are mapped, but no
 * use case has been written yet that uses them.
 *
 * The alternative was to repeat the whole `services.yaml` registration inside
 * a `when@test` block. That worked until a service needed explicit arguments:
 * re-registering the namespace rebuilds every definition from scratch and
 * silently drops them. A test environment that quietly wires things
 * differently from production is worse than no test environment.
 */
final class PublicServicesForTestsPass implements CompilerPassInterface
{
    private const NAMESPACE_PREFIX = 'LectoresBeta\\';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, self::NAMESPACE_PREFIX) && $this->isInstantiable($definition)) {
                $definition->setPublic(true);
            }
        }

        // The aliases matter as much as the definitions: a test asks for
        // `UserRepository`, which is an alias to its Doctrine implementation.
        foreach ($container->getAliases() as $id => $alias) {
            if (str_starts_with($id, self::NAMESPACE_PREFIX)) {
                $alias->setPublic(true);
            }
        }
    }

    /**
     * Value objects hide their constructor behind named factories, and the
     * autoregistration picks them up along with everything else. Symfony
     * drops them silently because nothing injects them — until this pass
     * makes them public, and then it refuses to build a service whose
     * constructor it cannot call.
     *
     * Skipping what cannot be instantiated keeps that from becoming a list of
     * exclusions that somebody has to remember to extend.
     */
    private function isInstantiable(Definition $definition): bool
    {
        $class = $definition->getClass();

        if (null === $class || !class_exists($class)) {
            return false;
        }

        $constructor = (new \ReflectionClass($class))->getConstructor();

        return null === $constructor || $constructor->isPublic();
    }
}
