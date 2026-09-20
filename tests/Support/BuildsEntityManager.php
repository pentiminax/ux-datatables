<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Boots an in-memory Doctrine entity manager mapped on tests/Fixtures/Count.
 *
 * Seeding stays in the test: only the bootstrap is shared.
 *
 * @internal
 */
trait BuildsEntityManager
{
    /**
     * @param class-string ...$entityClasses the entities whose schema is created
     */
    protected function createEntityManager(string ...$entityClasses): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__.'/../Fixtures/Count'],
            isDevMode: true,
        );

        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $em = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );

        (new SchemaTool($em))->createSchema(
            array_map($em->getClassMetadata(...), $entityClasses)
        );

        return $em;
    }
}
