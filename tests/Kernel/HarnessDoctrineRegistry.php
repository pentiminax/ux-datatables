<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Proxy;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountAccount;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;

/**
 * Serves the harness tables with an in-memory database holding the accounts 1 and 2 and the
 * account 3, which the scoped table hides.
 */
final class HarnessDoctrineRegistry extends AbstractManagerRegistry
{
    use BuildsEntityManager;

    private ?EntityManagerInterface $em = null;

    public function __construct()
    {
        parent::__construct('harness', ['default' => 'connection'], ['default' => 'manager'], 'default', 'default', Proxy::class);
    }

    protected function getService(string $name): object
    {
        return $this->em ??= $this->seededEntityManager();
    }

    protected function resetService(string $name): void
    {
        $this->em = null;
    }

    private function seededEntityManager(): EntityManagerInterface
    {
        $em = $this->createEntityManager(CountAccount::class);

        foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'hidden'] as $id => $name) {
            $em->persist(new CountAccount($id, $name));
        }

        $em->flush();
        $em->clear();

        return $em;
    }
}
