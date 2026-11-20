<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;

/**
 * A real RowScopeGuard that lets every row through, for tests that are not about the table scope.
 *
 * The tables under test must call unscopedMutations(), so the guard never queries a provider.
 *
 * @internal
 */
trait BuildsUnscopedRowScopeGuard
{
    use BuildsRuntime;

    protected function unscopedRowScopeGuard(?DataTableRuntime $runtime = null): RowScopeGuard
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifier')->willReturn(['id']);

        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturn($metadata);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($manager);

        return new RowScopeGuard(new EntityLocator($doctrine), $runtime ?? self::buildRuntime());
    }
}
