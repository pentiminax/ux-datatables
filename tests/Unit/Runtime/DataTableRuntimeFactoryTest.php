<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTableRuntimeFactory::class)]
final class DataTableRuntimeFactoryTest extends TestCase
{
    #[Test]
    public function create_row_mapper_returns_row_processing_pipeline(): void
    {
        $factory    = new DataTableRuntimeFactory();
        $baseMapper = static fn (mixed $row): array => ['id' => $row];

        $mapper = $factory->createRowMapper($baseMapper, []);

        $this->assertInstanceOf(RowMapperInterface::class, $mapper);
        $this->assertInstanceOf(RowProcessingPipeline::class, $mapper);
    }

    #[Test]
    public function create_row_mapper_applies_base_mapper(): void
    {
        $factory    = new DataTableRuntimeFactory();
        $baseMapper = static fn (mixed $row): array => ['value' => $row * 2];

        $mapper = $factory->createRowMapper($baseMapper, []);

        $this->assertSame(['value' => 10], $mapper->map(5));
    }

    #[Test]
    public function create_runtime_returns_data_table_runtime(): void
    {
        $factory = new DataTableRuntimeFactory();
        $table   = new DataTable('movies');

        $runtime = $factory->createRuntime(
            table: $table,
            columns: [],
            asDataTable: null,
            baseMapper: static fn ($r): array => [],
            manualDataProviderFactory: static fn (): ?DataProviderInterface => null,
            queryBuilderConfigurator: static fn ($qb, $req) => $qb,
        );

        $this->assertInstanceOf(DataTableRuntime::class, $runtime);
    }

    #[Test]
    public function create_runtime_is_lazy_provider_factory_not_called_before_get_data_provider(): void
    {
        $factoryCalls = 0;
        $factory      = new DataTableRuntimeFactory();

        $runtime = $factory->createRuntime(
            table: new DataTable('movies'),
            columns: [],
            asDataTable: null,
            baseMapper: static fn ($r): array => [],
            manualDataProviderFactory: static function () use (&$factoryCalls): ?DataProviderInterface {
                ++$factoryCalls;

                return null;
            },
            queryBuilderConfigurator: static fn ($qb, $req) => $qb,
        );

        $this->assertSame(0, $factoryCalls, 'Factory must not be called before getDataProvider()');

        $runtime->getDataProvider();

        $this->assertSame(1, $factoryCalls, 'Factory must be called exactly once on first getDataProvider()');

        $runtime->getDataProvider();

        $this->assertSame(1, $factoryCalls, 'Factory must not be called again on subsequent getDataProvider()');
    }

    #[Test]
    public function create_runtime_returns_manual_provider_when_supplied(): void
    {
        $manualProvider = new class implements DataProviderInterface {
            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
            }
        };

        $factory = new DataTableRuntimeFactory();
        $runtime = $factory->createRuntime(
            table: new DataTable('movies'),
            columns: [],
            asDataTable: null,
            baseMapper: static fn ($r): array => [],
            manualDataProviderFactory: static fn (): ?DataProviderInterface => $manualProvider,
            queryBuilderConfigurator: static fn ($qb, $req) => $qb,
        );

        $this->assertSame($manualProvider, $runtime->getDataProvider());
    }

    #[Test]
    public function create_runtime_returns_null_provider_when_no_manual_provider_and_no_asset_data_table(): void
    {
        $factory = new DataTableRuntimeFactory();
        $runtime = $factory->createRuntime(
            table: new DataTable('movies'),
            columns: [],
            asDataTable: null,
            baseMapper: static fn ($r): array => [],
            manualDataProviderFactory: static fn (): ?DataProviderInterface => null,
            queryBuilderConfigurator: static fn ($qb, $req) => $qb,
        );

        $this->assertNull($runtime->getDataProvider());
    }

    #[Test]
    public function set_entity_manager_enables_auto_provider_resolution(): void
    {
        $em      = $this->createMock(EntityManagerInterface::class);
        $factory = new DataTableRuntimeFactory();

        $factory->setEntityManager($em);

        $runtime = $factory->createRuntime(
            table: new DataTable('movies'),
            columns: [],
            asDataTable: new AsDataTable(entityClass: 'App\Entity\Movie'),
            baseMapper: static fn ($r): array => [],
            manualDataProviderFactory: static fn (): ?DataProviderInterface => null,
            queryBuilderConfigurator: static fn ($qb, $req) => $qb,
        );

        // AutoDataProviderFactory::create() returns a DoctrineDataProvider (not null)
        // only if the em was properly forwarded via setEntityManager()
        $this->assertInstanceOf(DataProviderInterface::class, $runtime->getDataProvider());
    }
}
