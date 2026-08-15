<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DataProviderResolver;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTableRuntimeFactory::class)]
final class DataTableRuntimeFactoryTest extends TestCase
{
    #[Test]
    public function create_row_mapper_returns_a_pipeline_applying_the_base_mapper(): void
    {
        $factory    = new DataTableRuntimeFactory();
        $baseMapper = static fn (mixed $row): array => ['value' => $row * 2];

        $mapper = $factory->createRowMapper($baseMapper, []);

        $this->assertInstanceOf(RowProcessingPipeline::class, $mapper);
        $this->assertSame(['value' => 10], $mapper->map(5));
    }

    #[Test]
    public function create_row_mapper_adds_boolean_switch_metadata(): void
    {
        $factory    = new DataTableRuntimeFactory();
        $baseMapper = static fn (mixed $row): array => ['active' => true];

        $mapper = $factory->createRowMapper($baseMapper, [
            BooleanColumn::new('active')->renderAsSwitch(),
        ]);

        $this->assertSame([
            'active'                           => true,
            '__ux_datatables_boolean_switches' => ['active' => 42],
        ], $mapper->map(new DataTableRuntimeFactoryBooleanSwitchFixture(42)));
    }

    #[Test]
    #[DataProvider('manualProviderCases')]
    public function create_runtime_returns_the_manual_data_provider(?DataProviderInterface $manualProvider): void
    {
        $runtime = $this->createRuntime(static fn (): ?DataProviderInterface => $manualProvider);

        $this->assertSame($manualProvider, $runtime->getDataProvider());
    }

    /**
     * @return iterable<string, array{0: ?DataProviderInterface}>
     */
    public static function manualProviderCases(): iterable
    {
        yield 'no manual provider and no AsDataTable attribute' => [null];

        yield 'manual provider supplied' => [
            new class implements DataProviderInterface {
                public function fetchData(DataTableRequest $request): DataTableResult
                {
                    return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
                }
            },
        ];
    }

    #[Test]
    public function create_runtime_resolves_the_data_provider_lazily_and_only_once(): void
    {
        $factoryCalls = 0;

        $runtime = $this->createRuntime(static function () use (&$factoryCalls): ?DataProviderInterface {
            ++$factoryCalls;

            return null;
        });

        $this->assertSame(0, $factoryCalls, 'Factory must not be called before getDataProvider()');

        $runtime->getDataProvider();

        $this->assertSame(1, $factoryCalls, 'Factory must be called exactly once on first getDataProvider()');

        $runtime->getDataProvider();

        $this->assertSame(1, $factoryCalls, 'Factory must not be called again on subsequent getDataProvider()');
    }

    #[Test]
    public function injected_data_provider_resolver_enables_auto_provider_resolution(): void
    {
        $runtime = $this->createRuntime(
            asDataTable: new AsDataTable(entityClass: 'App\Entity\Movie'),
            dataProviderResolver: new DataProviderResolver(
                new AutoDataProviderFactory($this->createStub(EntityManagerInterface::class))
            ),
        );

        $this->assertInstanceOf(DataProviderInterface::class, $runtime->getDataProvider());
    }

    private function createRuntime(
        ?\Closure $manualDataProviderFactory = null,
        ?AsDataTable $asDataTable = null,
        ?DataProviderResolver $dataProviderResolver = null,
    ): DataTableRuntime {
        return (new DataTableRuntimeFactory(dataProviderResolver: $dataProviderResolver))->createRuntime(
            table: new DataTable('movies'),
            columns: [],
            asDataTable: $asDataTable,
            baseMapper: static fn ($r): array => [],
            manualDataProviderFactory: $manualDataProviderFactory ?? static fn (): ?DataProviderInterface => null,
            configureQueryBuilder: static fn ($qb, $req) => $qb,
        );
    }
}

final class DataTableRuntimeFactoryBooleanSwitchFixture
{
    public function __construct(
        private readonly int $id,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
