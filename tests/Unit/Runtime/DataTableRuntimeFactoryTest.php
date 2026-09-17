<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\StreamingDataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ApiPlatformCollectionProvider;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Runtime\SearchListOptionsResolver;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsApiPlatformProviderFactory;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(DataTableRuntimeFactory::class)]
final class DataTableRuntimeFactoryTest extends TestCase
{
    use BuildsApiPlatformProviderFactory;
    use BuildsEntityManager;

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
    public function create_runtime_only_resolves_search_list_options_for_permitted_columns(): void
    {
        $seen     = [];
        $provider = static function (DataTableRequest $request, ColumnInterface $column) use (&$seen): array {
            $seen[] = $column->getName();

            return [$column->getName()];
        };
        $public = TextColumn::new('public');
        $secret = TextColumn::new('secret')->setPermission('ROLE_ADMIN');
        $table  = (new DataTable('records'))
            ->columns([$public, $secret])
            ->addExtension((new ColumnControlExtension([]))->add(1, [
                SearchList::new()->ajaxOptionsProvider($provider),
            ]));
        $symfonyChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $symfonyChecker->method('isGranted')->willReturn(false);
        $permissionChecker = new AuthorizationChecker($symfonyChecker);
        $runtime           = (new DataTableRuntimeFactory(
            permissionChecker: $permissionChecker,
            searchListOptionsResolver: new SearchListOptionsResolver(
                columnResolver: new ColumnResolver(
                    permissionChecker: $permissionChecker,
                ),
            ),
        ))->createRuntime(
            table: $table,
            columns: [$public, $secret],
            asDataTable: null,
            baseMapper: static fn (): array => [],
            manualDataProviderFactory: static fn (): DataProviderInterface => new class implements DataProviderInterface {
                public function fetchData(DataTableRequest $request): DataTableResult
                {
                    return new DataTableResult(0, 0, []);
                }
            },
            configureQueryBuilder: static fn ($qb, $request) => $qb,
        );
        $runtime->handleRequest(new Request(query: ['draw' => 1]));

        $payload = json_decode((string) $runtime->getResponse()->getContent(), true);

        $this->assertSame(['public'], $seen);
        $this->assertSame([
            'public' => [['label' => 'public', 'value' => 'public']],
        ], $payload['columnControl']);
    }

    #[Test]
    public function injected_auto_data_provider_factory_enables_auto_provider_resolution(): void
    {
        $runtime = $this->createRuntime(
            asDataTable: new AsDataTable(entityClass: \stdClass::class),
            autoDataProviderFactory: new AutoDataProviderFactory($this->createStub(EntityManagerInterface::class)),
        );

        $this->assertInstanceOf(DataProviderInterface::class, $runtime->getDataProvider());
    }

    #[Test]
    public function create_runtime_prioritizes_the_manual_provider_over_auto_configuration(): void
    {
        $manualProvider = $this->createMock(DataProviderInterface::class);

        $runtime = $this->createRuntime(
            manualDataProviderFactory: static fn (): DataProviderInterface => $manualProvider,
            asDataTable: new AsDataTable(entityClass: \stdClass::class),
            autoDataProviderFactory: new AutoDataProviderFactory($this->createStub(EntityManagerInterface::class)),
        );

        $this->assertSame($manualProvider, $runtime->getDataProvider());
    }

    /**
     * An export writes the exportable columns only, so the rows it streams must not pay for the
     * work the displayed table needs: one Twig render and one action resolution (voters, URLs,
     * CSRF tokens) per row, repeated over a whole table rather than a page.
     */
    #[Test]
    public function exported_rows_skip_template_rendering_and_action_resolution(): void
    {
        $em = $this->createEntityManager(CountCustomer::class, CountTag::class);
        $em->persist(new CountCustomer(1, 'Alpha'));
        $em->flush();
        $em->clear();

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('getToken');

        $runtime = (new DataTableRuntimeFactory(
            autoDataProviderFactory: new AutoDataProviderFactory($em),
            templateColumnRenderer: new TemplateColumnRenderer($twig),
            actionRowDataResolver: new ActionRowDataResolver(csrfTokenManager: $csrfTokenManager),
        ))->createRuntime(
            table: new DataTable('customers'),
            columns: [
                TextColumn::new('name'),
                TemplateColumn::new('badge')->setTemplate('badge.html.twig'),
                ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete())),
            ],
            asDataTable: new AsDataTable(entityClass: CountCustomer::class),
            baseMapper: static fn (mixed $row): array => ['name' => $row->name],
            manualDataProviderFactory: static fn (): ?DataProviderInterface => null,
            configureQueryBuilder: static fn ($qb, $request) => $qb,
        );

        $provider = $runtime->getDataProvider();
        $this->assertInstanceOf(StreamingDataProviderInterface::class, $provider);

        $rows = iterator_to_array($provider->iterateRows(new DataTableRequest(
            draw: 1,
            columns: new Columns([]),
            start: 0,
            length: 10,
        )), false);

        $this->assertSame([['name' => 'Alpha']], $rows);
    }

    /**
     * The `apiPlatform` option is set by the RenderingPreparer, which an Ajax request never runs:
     * reading it alone sent every attribute-declared table to Doctrine, bypassing the collection
     * operation's security, state provider and query extensions.
     */
    #[Test]
    public function the_attribute_alone_selects_the_api_platform_provider(): void
    {
        $runtime = $this->createRuntime(
            asDataTable: new AsDataTable(entityClass: \stdClass::class, apiPlatform: true),
            autoDataProviderFactory: new AutoDataProviderFactory(
                $this->createStub(EntityManagerInterface::class),
                $this->buildApiPlatformProviderFactory(),
            ),
        );

        $this->assertNull((new DataTable('movies'))->getOption('apiPlatform'));
        $this->assertInstanceOf(ApiPlatformCollectionProvider::class, $runtime->getDataProvider());
    }

    #[Test]
    public function it_keeps_the_doctrine_provider_without_the_api_platform_opt_in(): void
    {
        $runtime = $this->createRuntime(
            asDataTable: new AsDataTable(entityClass: \stdClass::class),
            autoDataProviderFactory: new AutoDataProviderFactory(
                $this->createStub(EntityManagerInterface::class),
                $this->buildApiPlatformProviderFactory(),
            ),
        );

        $this->assertNotInstanceOf(ApiPlatformCollectionProvider::class, $runtime->getDataProvider());
    }

    private function createRuntime(
        ?\Closure $manualDataProviderFactory = null,
        ?AsDataTable $asDataTable = null,
        ?AutoDataProviderFactory $autoDataProviderFactory = null,
    ): DataTableRuntime {
        return (new DataTableRuntimeFactory(autoDataProviderFactory: $autoDataProviderFactory))->createRuntime(
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
