<?php

declare(strict_types=1);

use LectoresBeta\Shared\Infrastructure\Symfony\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__).'/vendor/autoload.php';

/*
 * PHPStan boots the kernel through this file so that phpstan-doctrine can
 * read the real mapping metadata.
 *
 * It is what makes static analysis understand that a property the ORM
 * hydrates is read even though no PHP code reads it, instead of reporting a
 * hundred-odd false «never read, only written» errors — and, more usefully,
 * it also checks DQL and the field names passed to findBy().
 *
 * No database is opened: only the mapping is read. The environment still has
 * to be loaded, because the connection is configured from DATABASE_URL and
 * the container refuses to build without it.
 */
(new Dotenv())->bootEnv(\dirname(__DIR__).'/.env', 'test');

$kernel = new Kernel('test', false);
$kernel->boot();

$doctrine = $kernel->getContainer()->get('doctrine');
\assert($doctrine instanceof Doctrine\Persistence\ManagerRegistry);

return $doctrine->getManager();
