<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DataProviderResolver;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AutoDataProviderFactory::class)]
#[CoversClass(DataProviderResolver::class)]
final class DataProviderResolverTest extends TestCase
{
    #[Test]
    public function it_prioritizes_manual_provider_over_auto_configuration(): void
    {
        $manualProvider = $this->createMock(DataProviderInterface::class);

        $provider = $this->resolve(
            manualDataProvider: $manualProvider,
            asDataTable: new AsDataTable(entityClass: \stdClass::class),
        );

        $this->assertSame($manualProvider, $provider);
    }

    #[Test]
    public function it_auto_configures_a_doctrine_provider_when_attribute_and_entity_manager_are_available(): void
    {
        $provider = $this->resolve(
            asDataTable: new AsDataTable(entityClass: \stdClass::class),
            em: $this->createMock(EntityManagerInterface::class),
        );

        $this->assertInstanceOf(DoctrineDataProvider::class, $provider);
    }

    #[Test]
    public function it_returns_null_without_attribute(): void
    {
        $this->assertNull($this->resolve());
    }

    #[Test]
    public function it_throws_when_auto_configuration_requires_an_entity_manager(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('EntityManagerInterface is required to auto-configure a DoctrineDataProvider');

        $this->resolve(asDataTable: new AsDataTable(entityClass: \stdClass::class));
    }

    private function resolve(
        ?DataProviderInterface $manualDataProvider = null,
        ?AsDataTable $asDataTable = null,
        ?EntityManagerInterface $em = null,
    ): ?DataProviderInterface {
        return (new DataProviderResolver(new AutoDataProviderFactory($em)))->resolve(
            manualDataProvider: $manualDataProvider,
            asDataTable: $asDataTable,
            rowMapper: new DefaultRowMapper([]),
            configureQueryBuilder: static fn ($qb, $request) => $qb,
        );
    }
}
