<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\AbstractManagerRegistry;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountAccount;

/**
 * Serves the harness tables with an in-memory database holding the accounts 1 and 2 and the
 * account 3, which the scoped table hides.
 */
final class HarnessDoctrineRegistry extends AbstractManagerRegistry
{
    private ?EntityManagerInterface $em = null;

    public function __construct()
    {
        parent::__construct('harness', ['default' => 'connection'], ['default' => 'manager'], 'default', 'default');
    }

    protected function getService(string $name): object
    {
        return $this->em ??= $this->createEntityManager();
    }

    protected function resetService(string $name): void
    {
        $this->em = null;
    }

    private function createEntityManager(): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(paths: [__DIR__.'/../Fixtures/Count'], isDevMode: true);

        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(CountAccount::class)]);

        foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'hidden'] as $id => $name) {
            $em->persist(new CountAccount($id, $name));
        }

        $em->flush();
        $em->clear();

        return $em;
    }
}
