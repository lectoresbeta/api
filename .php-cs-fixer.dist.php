<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // AGENTS.md exige declare(strict_types=1) en todo el código.
        'declare_strict_types' => true,

        // Imports ordenados y sin sobrantes.
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Comparaciones estrictas: una comparación laxa en una regla de
        // negocio es un bug esperando a ocurrir.
        'strict_comparison' => true,
        'strict_param' => true,

        // Comas finales: hacen los diffs legibles.
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters'],
        ],

        // Orden predecible de miembros.
        'ordered_class_elements' => true,
        'ordered_types' => ['null_adjustment' => 'always_last'],

        'phpdoc_to_comment' => false,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
    ])
    ->setFinder($finder)
    ->setCacheFile('var/cache/.php-cs-fixer.cache');
