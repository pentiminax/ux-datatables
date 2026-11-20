<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableDefaultsConfiguratorInterface;
use Pentiminax\UX\DataTables\DataProvider\ApiPlatformCollectionProvider;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Highlight\HighlightConfig;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsApiPlatformProviderFactory;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use Pentiminax\UX\DataTables\Tests\Unit\Column\TestStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(DataTableRuntime::class)]
final class DataTableRuntimeTest extends TestCase
{
    use BuildsApiPlatformProviderFactory;
    use BuildsEntityManager;
    use BuildsRuntime;

    #[Test]
    public function it_answers_a_datatables_request_with_the_provider_result(): void
    {
        $table = new RuntimeTestTable(provider: self::provider(new DataTableResult(10, 4, [['id' => 1, 'name' => 'Alien'], ['id' => 2, 'name' => 'Heat']])));

        $response = self::buildRuntime()->respond($table, new Request(query: ['draw' => 3]));

        $this->assertSame([
            'draw'            => 3,
            'recordsTotal'    => 10,
            'recordsFiltered' => 4,
            'data'            => [['id' => 1, 'name' => 'Alien'], ['id' => 2, 'name' => 'Heat']],
        ], self::decode($response));
    }

    #[Test]
    public function it_answers_an_empty_page_when_the_table_has_no_provider(): void
    {
        $response = self::buildRuntime()->respond(new RuntimeTestTable(), new Request(query: ['draw' => 7]));

        $this->assertSame(['draw' => 7, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []], self::decode($response));
    }

    #[Test]
    public function it_answers_nothing_when_the_request_is_not_a_datatables_request(): void
    {
        $runtime = self::buildRuntime();
        $table   = new RuntimeTestTable(provider: self::provider(new DataTableResult(1, 1, [['id' => 1]])));

        $this->assertNull($runtime->respond($table, new Request()));
        $this->assertNull($runtime->respond($table, new Request(query: ['draw' => 0])));
    }

    #[Test]
    public function a_call_bound_to_no_request_answers_the_first_empty_draw(): void
    {
        $call = self::buildRuntime()->open(new RuntimeTestTable());

        $this->assertFalse($call->isHandled());
        $this->assertSame(['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []], self::decode($call->response()));
    }

    #[Test]
    public function it_binds_the_http_request_to_the_hooks_of_the_call_only(): void
    {
        $runtime = self::buildRuntime();
        $table   = new RuntimeTestTable();
        $request = new Request(query: ['draw' => 7, 'genre' => 'sci-fi']);

        $this->assertSame($request, $runtime->handle($table, $request)->hooks->probeHttpRequest());
        $this->assertNull($runtime->open($table)->hooks->probeHttpRequest());
        $this->assertNull($table->probeHttpRequest());
    }

    #[Test]
    public function it_serves_the_columns_a_request_may_see_and_decides_again_for_the_next_one(): void
    {
        $granted = new \ArrayObject(['secret' => false]);
        $symfony = new class($granted) implements AuthorizationCheckerInterface {
            public function __construct(private readonly \ArrayObject $granted)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?\Symfony\Component\Security\Core\Authorization\AccessDecision $accessDecision = null): bool
            {
                return $this->granted['secret'] || 'ROLE_ADMIN' !== $attribute;
            }
        };
        $runtime = self::buildRuntime(checker: new AuthorizationChecker($symfony));
        $table   = new RuntimeTestTable([TextColumn::new('id'), TextColumn::new('secret')->permission('ROLE_ADMIN')]);
        $request = new Request(query: ['draw' => 1]);

        $this->assertSame(['id'], $runtime->handle($table, $request)->hooks->probeColumnNames());

        $granted['secret'] = true;

        $this->assertSame(['id', 'secret'], $runtime->handle($table, $request)->hooks->probeColumnNames());
        $this->assertSame(['id', 'secret'], array_map(static fn (ColumnInterface $column): string => $column->getName(), $runtime->definition($table)->columns));
    }

    #[Test]
    public function it_resolves_the_provider_lazily_and_once_per_call(): void
    {
        $table = new RuntimeTestTable(provider: self::provider());
        $call  = self::buildRuntime()->handle($table, new Request(query: ['draw' => 1]));

        $this->assertSame(0, $table->calls['provider']);

        $provider = $call->dataProvider();

        $this->assertSame($provider, $call->dataProvider());
        $this->assertSame(1, $table->calls['provider']);
    }

    #[Test]
    public function it_resolves_no_provider_for_a_table_without_one_or_an_attribute(): void
    {
        $this->assertNull(self::buildRuntime()->open(new RuntimeTestTable())->dataProvider());
    }

    #[Test]
    public function the_manual_provider_wins_over_the_auto_configured_one(): void
    {
        $manual = self::provider();
        $table  = new RuntimeEntityTable(provider: $manual);

        $call = self::buildRuntime(autoDataProviderFactory: new AutoDataProviderFactory($this->createStub(EntityManagerInterface::class)))->open($table);

        $this->assertSame($manual, $call->dataProvider());
    }

    #[Test]
    public function the_attribute_alone_selects_a_doctrine_provider(): void
    {
        $runtime = self::buildRuntime(autoDataProviderFactory: new AutoDataProviderFactory(
            $this->createStub(EntityManagerInterface::class),
            $this->buildApiPlatformProviderFactory(),
        ));

        $this->assertInstanceOf(DoctrineDataProvider::class, $runtime->open(new RuntimeEntityTable())->dataProvider());
    }

    /**
     * The `apiPlatform` option is set by the RenderingPreparer, which an Ajax request never runs:
     * reading it alone sent every attribute-declared table to Doctrine, bypassing the collection
     * operation's security, state provider and query extensions.
     */
    #[Test]
    public function the_attribute_alone_selects_the_api_platform_provider(): void
    {
        $runtime = $this->apiPlatformRuntime();
        $table   = new RuntimeApiPlatformTable();

        $this->assertNull($runtime->definition($table)->table->getOption('apiPlatform'));
        $this->assertInstanceOf(ApiPlatformCollectionProvider::class, $runtime->open($table)->dataProvider());
    }

    #[Test]
    public function an_api_platform_table_without_a_collection_answers_not_found(): void
    {
        $runtime = self::buildRuntime(autoDataProviderFactory: new AutoDataProviderFactory(
            $this->createStub(EntityManagerInterface::class),
            $this->buildApiPlatformProviderFactory(),
        ));
        $table = new RuntimeApiPlatformTable();

        $this->expectException(NotFoundHttpException::class);

        $runtime->respond($table, new Request(query: ['draw' => 1]));
    }

    #[Test]
    public function it_adds_summaries_to_the_response_only_when_the_provider_returns_some(): void
    {
        $summariesOf = function (?array $summaries): array {
            $table    = new RuntimeTestTable(provider: self::provider(new DataTableResult(1, 1, [['total' => 5]], $summaries)));
            $response = self::buildRuntime()->respond($table, new Request(query: ['draw' => 2]));

            return self::decode($response);
        };

        $this->assertSame(['total' => ['sum' => 5]], $summariesOf(['total' => ['sum' => 5]])['summaries']);
        $this->assertArrayNotHasKey('summaries', $summariesOf(null));
        $this->assertArrayNotHasKey('summaries', $summariesOf([]));
    }

    #[Test]
    public function it_adds_search_list_options_for_the_columns_the_request_may_see(): void
    {
        $seen     = [];
        $provider = static function (DataTableRequest $request, ColumnInterface $column) use (&$seen): array {
            $seen[] = $column->getName();

            return [$column->getName()];
        };
        $table = new RuntimeTestTable(
            [TextColumn::new('public'), TextColumn::new('secret')->permission('ROLE_ADMIN')],
            provider: self::provider(new DataTableResult(0, 0, [])),
            configureTable: static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(1, [
                SearchList::new()->ajaxOptionsProvider($provider),
            ])),
        );
        $symfony = $this->createStub(AuthorizationCheckerInterface::class);
        $symfony->method('isGranted')->willReturn(false);

        $response = self::buildRuntime(checker: new AuthorizationChecker($symfony))->respond($table, new Request(query: ['draw' => 1]));

        $this->assertSame(['public'], $seen);
        $this->assertSame(['public' => [['label' => 'public', 'value' => 'public']]], self::decode($response)['columnControl']);
    }

    #[Test]
    #[DataProvider('showAllLengthMenus')]
    public function it_serves_show_all_only_when_the_length_menu_offers_it(array $lengthMenu): void
    {
        $call = $this->handleLength(-1, static fn (DataTable $table): DataTable => $table->lengthMenu($lengthMenu), query: ['start' => -5]);

        $this->assertSame(-1, $call->request?->length);
        $this->assertSame(0, $call->request?->start);
    }

    /**
     * @return iterable<string, array{0: array<mixed>}>
     */
    public static function showAllLengthMenus(): iterable
    {
        yield 'flat menu' => [[10, 25, -1]];
        yield 'menu with its own labels' => [[[10, 25, -1], ['10', '25', 'All']]];
        yield 'menu with an object entry' => [[10, 25, ['label' => 'All', 'value' => -1]]];
        yield 'menu starting with an object entry' => [[
            ['label' => 'All', 'value' => -1],
            ['label' => '25', 'value' => 25],
        ]];
    }

    #[Test]
    #[DataProvider('invalidShowAllLengthMenus')]
    public function it_caps_show_all_for_malformed_object_entries(array $lengthMenu): void
    {
        $call = $this->handleLength(-1, static fn (DataTable $table): DataTable => $table->lengthMenu($lengthMenu));

        $this->assertSame(100, $call->request?->length);
    }

    /**
     * @return iterable<string, array{0: array<mixed>}>
     */
    public static function invalidShowAllLengthMenus(): iterable
    {
        yield 'missing value' => [[10, ['label' => 'All']]];
        yield 'string value' => [[10, ['label' => 'All', 'value' => '-1']]];
        yield 'different value' => [[10, ['label' => 'All', 'value' => -2]]];
    }

    #[Test]
    #[DataProvider('cappedLengths')]
    public function it_caps_length_when_the_length_menu_has_no_show_all(int $length, int $expected): void
    {
        $call = $this->handleLength($length, static fn (DataTable $table): DataTable => $table->lengthMenu([10, 25]));

        $this->assertSame($expected, $call->request?->length);
    }

    /**
     * @return iterable<string, array{0: int, 1: int}>
     */
    public static function cappedLengths(): iterable
    {
        yield 'show all is capped' => [-1, 100];
        yield 'an oversized length is capped' => [999999, 100];
        yield 'a length within bounds is kept' => [25, 25];
    }

    /**
     * A declared page size is a developer decision, and the client paginates with it: capping it
     * below that size left the rows between the two lengths on no page at all.
     */
    #[Test]
    #[DataProvider('declaredPageLengths')]
    public function it_keeps_a_page_length_the_table_declares(\Closure $configure, int $length, int $expected): void
    {
        $this->assertSame($expected, $this->handleLength($length, $configure)->request?->length);
    }

    /**
     * @return iterable<string, array{0: \Closure(DataTable): DataTable, 1: int, 2: int}>
     */
    public static function declaredPageLengths(): iterable
    {
        yield 'pageLength above the bound is served' => [static fn (DataTable $table): DataTable => $table->pageLength(2000), 2000, 2000];
        yield 'a length menu entry above the bound is served' => [static fn (DataTable $table): DataTable => $table->lengthMenu([500, 2000]), 2000, 2000];
        yield 'an undeclared length is still capped to the bound' => [static fn (DataTable $table): DataTable => $table->pageLength(2000), 999999, 100];
        yield 'a crafted length below a declared size is still capped' => [static fn (DataTable $table): DataTable => $table->pageLength(2000), 1500, 100];
        yield 'show all is still capped to the configured bound' => [static fn (DataTable $table): DataTable => $table->pageLength(2000), -1, 100];
    }

    #[Test]
    public function it_hydrates_a_client_side_table_with_the_rows_of_its_provider_only(): void
    {
        $provider = new class implements DataProviderInterface {
            public ?bool $rowsOnly = null;

            public function supports(DataSourceFeature $feature): bool
            {
                return true;
            }

            public function iterateRows(DataTableRequest $request): iterable
            {
                return $this->fetchData($request)->data;
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                $this->rowsOnly = $request->rowsOnly;

                return new DataTableResult(2, 2, [['name' => 'Ada'], ['name' => 'Grace']]);
            }
        };

        $payload = self::buildRuntime()->render(new RuntimeTestTable([TextColumn::new('name')], provider: $provider))->getView();

        $this->assertSame([['name' => 'Ada'], ['name' => 'Grace']], $payload['data']);
        $this->assertTrue($provider->rowsOnly);
    }

    #[Test]
    #[DataProvider('hydrationThresholds')]
    public function it_warns_once_when_a_table_embeds_more_rows_than_the_threshold(int $threshold, bool $warns): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message];
            }
        };
        $table = new RuntimeTestTable([TextColumn::new('name')], provider: self::provider(new DataTableResult(2, 2, [['name' => 'Ada'], ['name' => 'Grace']])));

        self::buildRuntime(logger: $logger, hydrationRowThreshold: $threshold)->render($table);

        if (!$warns) {
            $this->assertSame([], $logger->records);

            return;
        }

        $this->assertCount(1, $logger->records);
        [$level, $message] = $logger->records[0];
        $this->assertSame('warning', $level);
        $this->assertStringContainsString(RuntimeTestTable::class, $message);
        $this->assertStringContainsString('2 rows', $message);
        $this->assertStringContainsString('serverSide()', $message);
        $this->assertStringNotContainsString('Ada', $message);
    }

    /**
     * @return iterable<string, array{0: int, 1: bool}>
     */
    public static function hydrationThresholds(): iterable
    {
        yield 'above the threshold' => [1, true];
        yield 'at the threshold' => [2, false];
        yield 'a zero threshold disables the warning' => [0, false];
    }

    #[Test]
    public function it_records_the_answered_ajax_query_with_the_profiler(): void
    {
        $profiler = new DataTableProfiler();
        $runtime  = self::buildRuntime(profiler: $profiler);
        $table    = new RuntimeTestTable(provider: self::provider(new DataTableResult(10, 4, [['id' => 1]])));

        $runtime->respond($table, new Request());
        $this->assertSame([], $profiler->getAjaxQueries());

        $runtime->respond($table, new Request(query: ['draw' => 3]));

        $queries = $profiler->getAjaxQueries();

        $this->assertCount(1, $queries);
        $this->assertSame(10, $queries[0]['recordsTotal']);
        $this->assertSame(4, $queries[0]['recordsFiltered']);
        $this->assertSame(1, $queries[0]['rowCount']);
    }

    #[Test]
    public function two_renders_of_the_same_table_give_the_same_payload(): void
    {
        $symfony = $this->createStub(AuthorizationCheckerInterface::class);
        $symfony->method('isGranted')->willReturn(true);
        $runtime = self::buildRuntime(checker: new AuthorizationChecker($symfony));
        $table   = new RuntimeTestTable(
            [TextColumn::new('id'), TextColumn::new('secret')->permission('ROLE_ADMIN')],
            provider: self::provider(new DataTableResult(1, 1, [['id' => 1, 'secret' => 'x']])),
            configureTable: static fn (DataTable $table): DataTable => $table->pageLength(25),
        );
        $definition = $runtime->definition($table);

        $first = $runtime->render($table)->getView();

        $this->assertSame($first, $runtime->render($table)->getView());
        $this->assertSame($definition, $runtime->definition($table));
        $this->assertSame(['id', 'secret'], array_keys($definition->table->getColumns()));
    }

    #[Test]
    public function it_refuses_to_render_a_permissioned_column_without_an_authorization_checker(): void
    {
        $table = new RuntimeTestTable([TextColumn::new('secret')->permission('ROLE_ADMIN')], configureTable: static fn (DataTable $table): DataTable => $table->data([['secret' => 'x']]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ROLE_ADMIN');

        self::buildRuntime()->render($table);
    }

    #[Test]
    public function it_stamps_the_bundle_defaults_onto_the_definition_of_every_table(): void
    {
        $runtime = self::buildRuntime(
            options: ['pageLength' => 25],
            attributes: ['class' => 'table table-striped'],
            extensions: ['select' => ['style' => 'multi']],
        );

        $first  = $runtime->definition(new RuntimeTestTable())->table;
        $second = $runtime->definition(new RuntimeTestTable())->table;

        $this->assertSame(25, $first->getOption('pageLength'));
        $this->assertSame(['class' => 'table table-striped'], $first->getAttributes());
        $this->assertSame('multi', $first->getExtensions()['select']['style']);
        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function the_defaults_configurators_run_in_order_and_the_table_keeps_the_last_word(): void
    {
        $runtime = self::buildRuntime(
            options: ['pageLength' => 25],
            defaultsConfigurators: [
                self::configurator(static fn (DataTable $table) => $table->pageLength(50)),
                self::configurator(static fn (DataTable $table) => $table->pageLength(75)->stateSave(true)),
            ],
        );

        $plain = $runtime->definition(new RuntimeTestTable())->table;
        $own   = $runtime->definition(new RuntimeTestTable(configureTable: static fn (DataTable $table): DataTable => $table->pageLength(10)))->table;

        $this->assertSame(75, $plain->getOption('pageLength'));
        $this->assertSame(10, $own->getOption('pageLength'));
        $this->assertTrue($own->getOption('stateSave'));
    }

    #[Test]
    public function the_row_mapper_applies_the_hook_of_the_table(): void
    {
        $table = new RuntimeTestTable(mapRow: static fn (mixed $row): array => ['value' => $row * 2]);

        $this->assertSame(['value' => 10], self::buildRuntime()->open($table)->rowMapper()->map(5));
        $this->assertSame(['value' => 10], self::buildRuntime()->open($table)->hooks->probeMapRow(5));
    }

    #[Test]
    public function the_row_mapper_adds_boolean_switch_metadata(): void
    {
        $table = new RuntimeTestTable([BooleanColumn::new('active')->renderAsSwitch()], mapRow: static fn (): array => ['active' => true]);

        $this->assertSame(
            ['active' => true, '__ux_datatables_boolean_switches' => ['active' => '42']],
            self::buildRuntime()->open($table)->rowMapper()->map(new RuntimeBooleanSwitchRow(42)),
        );
    }

    #[Test]
    public function the_row_mapper_formats_values_after_normalization(): void
    {
        $table = new RuntimeTestTable(
            [TextColumn::new('status')->formatValueUsing(static fn (string $value): string => strtoupper($value))],
            mapRow: static fn (): array => ['status' => TestStatus::Active],
        );

        $this->assertSame(['status' => 'ACTIVE'], self::buildRuntime()->open($table)->rowMapper()->map(new \stdClass()));
    }

    #[Test]
    public function the_row_mapper_adds_the_presentation_stage_only_when_a_callable_is_set(): void
    {
        $runtime = self::buildRuntime();
        $mapRow  = static fn (array $row): array => ['id' => $row['id']];

        $plain = $runtime->open(new RuntimeTestTable(mapRow: $mapRow))->rowMapper();
        $rich  = $runtime->open(new RuntimeTestTable(
            configureTable: static fn (DataTable $table): DataTable => $table
                ->rowClass(static fn (array $row): string => 'row-'.$row['id'])
                ->recordUrl(static fn (array $row): string => '/items/'.$row['id']),
            mapRow: $mapRow,
        ))->rowMapper();

        $this->assertSame(['id' => 4], $plain->map(['id' => 4]));
        $this->assertSame([
            'id'          => 4,
            'DT_RowClass' => 'row-4',
            'DT_RowAttr'  => ['data-record-url' => '/items/4'],
        ], $rich->map(['id' => 4]));
    }

    #[Test]
    public function the_row_mapper_uses_the_single_doctrine_identifier_for_bulk_rows(): void
    {
        $runtime = self::buildRuntime(doctrine: $this->registryWithIdentifier('uuid'));

        $mapper = $runtime->open(new RuntimeUuidTable())->rowMapper();

        $this->assertSame([HighlightConfig::ROW_ID_KEY => 'order-42'], $mapper->map(new RuntimeUuidRow('order-42')));
    }

    #[Test]
    public function the_bulk_payload_remaps_the_default_id_onto_the_doctrine_identifier(): void
    {
        $runtime = self::buildRuntime(
            autoDataProviderFactory: new AutoDataProviderFactory($this->createStub(EntityManagerInterface::class)),
            doctrine: $this->registryWithIdentifier('sku'),
        );

        $payload = $runtime->render(new RuntimeUuidTable())->getView();

        $this->assertSame('sku', $payload['bulkActions']['idField']);
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

        $runtime = self::buildRuntime(
            autoDataProviderFactory: new AutoDataProviderFactory($em),
            templateColumnRenderer: new TemplateColumnRenderer($twig),
            actionRowDataResolver: new ActionRowDataResolver(new AuthorizationChecker(), csrfTokenManager: $csrfTokenManager),
        );
        $table = new RuntimeCustomerTable(
            [
                TextColumn::new('name'),
                TemplateColumn::new('badge')->template('badge.html.twig'),
                ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete())),
            ],
            mapRow: static fn (mixed $row): array => ['name' => $row->name],
        );

        $provider = $runtime->open($table)->dataProvider();
        $this->assertInstanceOf(DataProviderInterface::class, $provider);

        $rows = iterator_to_array($provider->iterateRows(new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: 10)), false);

        $this->assertSame([['name' => 'Alpha']], $rows);
    }

    #[Test]
    public function the_export_mapper_does_not_receive_the_presentation_stage(): void
    {
        $table = new RuntimeApiPlatformTable(
            configureTable: static fn (DataTable $table): DataTable => $table
                ->rowClass(static fn (): string => 'late')
                ->recordUrl(static fn (): string => '/books/1'),
            mapRow: static fn (): array => ['title' => 'A'],
        );

        $provider = $this->apiPlatformRuntime()->open($table)->dataProvider();

        $this->assertInstanceOf(ApiPlatformCollectionProvider::class, $provider);

        $page   = (new \ReflectionProperty($provider, 'rowMapper'))->getValue($provider);
        $export = (new \ReflectionProperty($provider, 'exportRowMapper'))->getValue($provider);

        $this->assertSame(['DT_RowClass' => 'late', 'DT_RowAttr' => ['data-record-url' => '/books/1']], array_diff_key($page->map(null), ['title' => 1]));
        $this->assertSame(['title' => 'A'], $export->map(null));
    }

    /**
     * @param \Closure(DataTable): DataTable $configure
     * @param array<string, mixed>           $query
     */
    private function handleLength(int $length, \Closure $configure, array $query = []): \Pentiminax\UX\DataTables\Runtime\DataTableCall
    {
        return self::buildRuntime(maxPageLength: 100)->handle(
            new RuntimeTestTable(configureTable: $configure),
            new Request(query: ['draw' => 1, 'length' => $length, ...$query]),
        );
    }

    private function apiPlatformRuntime(): DataTableRuntime
    {
        $resolver = $this->createStub(ApiResourceCollectionUrlResolver::class);
        $resolver->method('resolveCollectionUrl')->willReturn('/api/books');

        return self::buildRuntime(
            autoDataProviderFactory: new AutoDataProviderFactory(
                $this->createStub(EntityManagerInterface::class),
                $this->buildApiPlatformProviderFactory(),
            ),
            collectionUrlResolver: $resolver,
        );
    }

    private function registryWithIdentifier(string $identifier): ManagerRegistry
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifier')->willReturn([$identifier]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return $registry;
    }

    private static function provider(?DataTableResult $result = null): DataProviderInterface
    {
        return new class($result ?? new DataTableResult(0, 0, [])) implements DataProviderInterface {
            public function __construct(private readonly DataTableResult $result)
            {
            }

            public function supports(DataSourceFeature $feature): bool
            {
                return true;
            }

            public function iterateRows(DataTableRequest $request): iterable
            {
                return $this->result->data;
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return $this->result;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(?\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        self::assertNotNull($response);

        return json_decode((string) $response->getContent(), true);
    }

    private static function configurator(\Closure $apply): DataTableDefaultsConfiguratorInterface
    {
        return new class($apply) implements DataTableDefaultsConfiguratorInterface {
            public function __construct(private readonly \Closure $apply)
            {
            }

            public function configure(DataTable $table): void
            {
                ($this->apply)($table);
            }
        };
    }
}

class RuntimeTestTable extends AbstractDataTable
{
    /** @var \ArrayObject<string, int> shared with the bound copy the runtime calls */
    public readonly \ArrayObject $calls;

    /**
     * @param list<ColumnInterface>                        $columns
     * @param (\Closure(DataTable): DataTable)|null        $configureTable
     * @param (\Closure(BulkActions): BulkActions)|null    $bulkActions
     * @param (\Closure(mixed): array<string, mixed>)|null $mapRow
     */
    public function __construct(
        private readonly array $columns = [],
        private readonly ?DataProviderInterface $provider = null,
        private readonly ?\Closure $configureTable = null,
        private readonly ?\Closure $mapRow = null,
        private readonly ?\Closure $bulkActions = null,
    ) {
        $this->calls = new \ArrayObject(['provider' => 0]);
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return null === $this->configureTable ? $table : ($this->configureTable)($table);
    }

    public function configureColumns(): iterable
    {
        return $this->columns;
    }

    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return null === $this->bulkActions ? $actions : ($this->bulkActions)($actions);
    }

    public function createDataProvider(): ?DataProviderInterface
    {
        ++$this->calls['provider'];

        return $this->provider;
    }

    public function mapRow(mixed $row): array
    {
        return null === $this->mapRow ? parent::mapRow($row) : ($this->mapRow)($row);
    }

    public function probeHttpRequest(): ?Request
    {
        return $this->getHttpRequest();
    }

    /**
     * @return list<string>
     */
    public function probeColumnNames(): array
    {
        return array_map(static fn (ColumnInterface $column): string => $column->getName(), $this->getResolvedColumns());
    }

    /**
     * @return array<string, mixed>
     */
    public function probeMapRow(mixed $row): array
    {
        return $this->createRowMapper()->map($row);
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class RuntimeEntityTable extends RuntimeTestTable
{
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class RuntimeApiPlatformTable extends RuntimeTestTable
{
}

#[AsDataTable(entityClass: CountCustomer::class)]
final class RuntimeCustomerTable extends RuntimeTestTable
{
}

#[AsDataTable(entityClass: RuntimeUuidRow::class)]
final class RuntimeUuidTable extends RuntimeTestTable
{
    public function __construct()
    {
        parent::__construct(
            configureTable: static fn (DataTable $table): DataTable => $table->unscopedMutations(),
            bulkActions: static fn (BulkActions $actions): BulkActions => $actions->add(BulkAction::new('touch')->handler(static fn () => null)),
        );
    }
}

final class RuntimeBooleanSwitchRow
{
    public function __construct(private readonly int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}

final class RuntimeUuidRow
{
    public function __construct(private readonly string $uuid)
    {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
