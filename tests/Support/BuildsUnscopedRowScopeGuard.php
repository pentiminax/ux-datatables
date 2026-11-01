<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;

/**
 * A real RowScopeGuard that lets every row through, for tests that are not about the table scope.
 *
 * Its own registry reports an entity without identifier, so the guard returns before any query.
 *
 * @internal
 */
trait BuildsUnscopedRowScopeGuard
{
    protected function unscopedRowScopeGuard(): RowScopeGuard
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifier')->willReturn([]);

        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturn($metadata);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($manager);

        return new RowScopeGuard(new EntityLocator($doctrine));
    }
}
