<?php

namespace App\Extensions\Hautelook\AliceBundle\Loader;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\Bridge\Doctrine\Persister\ObjectManagerPersister;
use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Hautelook\AliceBundle\Loader\DoctrineOrmLoader as BaseDoctrineOrmLoader;

/**
 * Some fixtures depend on other fixtures to have been persisted
 * This is true for relations which do not use foreign keys
 *
 * Usage: Clear database and add data from fixtures to database "kassa"
 * php bin/console hautelook:fixtures:load
 *
 * During tests, fixtures reload database "kassa_test".
 */
class DoctrineOrmLoader extends BaseDoctrineOrmLoader
{
    protected function loadFixtures(LoaderInterface $loader, EntityManagerInterface $manager, array $files, array $parameters, ?PurgeMode $purgeMode): array
    {
        /** @var ObjectManagerPersister $persister */
        $persister = $this->createPersister($manager);

        /** @var LoaderInterface $loader */
        $loader = $loader->withPersister($persister);
        $results = [];
        $currentPurgeMode = $purgeMode;
        foreach ($files as $file) {
            // Each load persists all objects to entity manager
            // Pass results to load() to allow referencing fixtures across files
            try {
                $results += $loader->load([$file], $parameters, $results, $currentPurgeMode);
            } catch (\Throwable $e) {
                $msg = sprintf("Fixture loading failed on file: %s\n%s", $file, $e->getMessage());
                foreach ($results as $key => $obj) {
                    $msg .= sprintf("\n  [%s] %s #%s", $key, get_class($obj), method_exists($obj, 'getId') ? $obj->getId() : '?');
                }
                throw new \RuntimeException($msg, 0, $e);
            }

            // Avoid any purges after the first fixtures have loaded
            $currentPurgeMode = PurgeMode::createNoPurgeMode();
        }

        return $results;
    }
}
