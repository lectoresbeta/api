<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(\dirname(__DIR__).'/.env');

/**
 * Empieza cada ejecución con los contadores de frecuencia a cero.
 *
 * Los limitadores del inicio de sesión son reales en los tests —desactivarlos
 * sería tanto como no probarlos— y guardan su contador en la caché de la
 * aplicación, que **sobrevive de un `phpunit` al siguiente**. Sin esto, dos o
 * tres ejecuciones seguidas agotan la cuota y empiezan a fallar tests que no
 * van de eso, con un `429` que parece un fallo de código.
 *
 * Se borra el almacén, no los límites: lo que se prueba sigue siendo el
 * comportamiento real.
 */
$pools = \dirname(__DIR__).'/var/cache/test/pools';

if (is_dir($pools)) {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pools, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        /** @var SplFileInfo $entry */
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
}
