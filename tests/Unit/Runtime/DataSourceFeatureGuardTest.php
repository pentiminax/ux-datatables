<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierDataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SearchBuilderExtension;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Model\Summary\Count;
use Pentiminax\UX\DataTables\Model\Summary\Sum;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Pentiminax\UX\DataTables\Runtime\DataSourceFeatureGuard;
use Pentiminax\UX\DataTables\Runtime\DataSourceMode;
use Pentiminax\UX\DataTables\Runtime\ResolvedOptIns;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @internal
 */
#[CoversClass(DataSourceFeatureGuard::class)]
final class DataSourceFeatureGuardTest extends TestCase
{
    use BuildsRuntime;
    use CollectsDeprecations;

    #[Test]
    #[DataProvider('apiPlatformRefusals')]
    public function it_refuses_at_render_what_an_api_platform_collection_cannot_apply(\Closure $configure, string $message): void
    {
        $table = $configure(new DataTable('books'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        (new DataSourceFeatureGuard())->assertConfigurable($table, self::optIns(DataSourceMode::ApiPlatform));
    }

    #[Test]
    public function it_applies_the_api_platform_capability_to_an_ajax_url_read_with_the_hydra_adapter(): void
    {
        $table = (new DataTable('books'))->addExtension(new SearchBuilderExtension());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SearchBuilder criteria are not supported on an API Platform collection');

        (new DataSourceFeatureGuard())->assertConfigurable($table, self::optIns(DataSourceMode::Ajax, apiPlatform: true));
    }

    #[Test]
    public function it_leaves_a_plain_ajax_url_alone(): void
    {
        $this->expectNotToPerformAssertions();

        (new DataSourceFeatureGuard())->assertConfigurable((new DataTable('books'))->addExtension(new SearchBuilderExtension()), self::optIns(DataSourceMode::Ajax, apiPlatform: false));
    }

    /**
     * @return iterable<string, array{\Closure(DataTable): DataTable, string}>
     */
    public static function apiPlatformRefusals(): iterable
    {
        yield 'SearchBuilder' => [
            static fn (DataTable $table): DataTable => $table->addExtension(new SearchBuilderExtension()),
            'SearchBuilder criteria are not supported on an API Platform collection',
        ];

        yield 'default ColumnControl, which holds a search' => [
            static fn (DataTable $table): DataTable => $table->addExtension(new ColumnControlExtension()),
            'ColumnControl searches are not supported on an API Platform collection',
        ];

        yield 'ColumnControl search nested in a dropdown' => [
            static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(0, [['extend' => 'dropdown', 'content' => ['searchText']]])),
            'ColumnControl searches are not supported on an API Platform collection',
        ];

        yield 'ColumnControl search set on one column' => [
            static fn (DataTable $table): DataTable => $table
                ->addExtension((new ColumnControlExtension([]))->add(0, ['order']))
                ->columns([TextColumn::new('title')->columnControl(['searchList'])]),
            'ColumnControl searches are not supported on an API Platform collection',
        ];

        yield 'SearchList object' => [
            static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(1, [SearchList::new()])),
            'ColumnControl searches are not supported on an API Platform collection',
        ];

        yield 'summaries' => [
            static fn (DataTable $table): DataTable => $table->columns([NumberColumn::new('price')->summarize(new Sum())]),
            'Column summaries are not supported on an API Platform collection',
        ];
    }

    #[Test]
    #[DataProvider('browserModes')]
    public function it_refuses_a_browser_summary_of_formatted_values(DataSourceMode $mode): void
    {
        $table = (new DataTable('orders'))->columns([
            NumberColumn::new('amount')->formatValueUsing(static fn (int $value): string => number_format($value, 2))->summarize(new Sum()),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "sum" summary of column "amount" cannot be computed in the browser');

        (new DataSourceFeatureGuard())->assertConfigurable($table, self::optIns($mode));
    }

    /**
     * @return iterable<string, array{DataSourceMode}>
     */
    public static function browserModes(): iterable
    {
        yield 'hydrated from the provider' => [DataSourceMode::ClientHydrated];
        yield 'inline data' => [DataSourceMode::Inline];
    }

    #[Test]
    #[DataProvider('acceptedConfigurations')]
    public function it_accepts_at_render_what_the_mode_can_apply(\Closure $configure, DataSourceMode $mode): void
    {
        $this->expectNotToPerformAssertions();

        (new DataSourceFeatureGuard())->assertConfigurable($configure(new DataTable('books')), self::optIns($mode));
    }

    /**
     * @return iterable<string, array{\Closure(DataTable): DataTable, DataSourceMode}>
     */
    public static function acceptedConfigurations(): iterable
    {
        yield 'ColumnControl without a search on API Platform' => [
            static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(0, ['order', ['orderAsc', 'orderDesc']])),
            DataSourceMode::ApiPlatform,
        ];

        yield 'a dropdown whose icon is named search' => [
            static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(0, [['extend' => 'dropdown', 'icon' => 'search', 'content' => ['orderAsc', 'orderDesc']]])),
            DataSourceMode::ApiPlatform,
        ];

        yield 'configured filters on API Platform' => [
            static fn (DataTable $table): DataTable => $table->setFilters((new Filters())->add(TextFilter::new('title'))),
            DataSourceMode::ApiPlatform,
        ];

        yield 'SearchBuilder in the browser' => [
            static fn (DataTable $table): DataTable => $table->addExtension(new SearchBuilderExtension()),
            DataSourceMode::ClientHydrated,
        ];

        yield 'a count of formatted values in the browser' => [
            static fn (DataTable $table): DataTable => $table->columns([NumberColumn::new('amount')->formatValueUsing(static fn (int $value): string => number_format($value))->summarize(new Count())]),
            DataSourceMode::ClientHydrated,
        ];

        yield 'a sum of formatted values on the server' => [
            static fn (DataTable $table): DataTable => $table->columns([NumberColumn::new('amount')->formatValueUsing(static fn (int $value): string => number_format($value))->summarize(new Sum())]),
            DataSourceMode::ServerSide,
        ];

        // No provider is built while rendering: a server-side table is checked on its first request.
        yield 'SearchBuilder on the server' => [
            static fn (DataTable $table): DataTable => $table->addExtension(new SearchBuilderExtension()),
            DataSourceMode::ServerSide,
        ];
    }

    #[Test]
    #[DataProvider('refusedRequests')]
    public function it_refuses_a_request_the_provider_cannot_apply(array $query, string $message): void
    {
        $table = self::table(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($message);

        self::buildRuntime()->handle($table, new Request(query: ['draw' => 1, ...$query]))->dataProvider();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedRequests(): iterable
    {
        yield 'SearchBuilder criteria' => [
            ['searchBuilder' => ['logic' => 'AND', 'criteria' => [['condition' => '=', 'origData' => 'name', 'type' => 'string', 'value' => ['Alice']]]]],
            'SearchBuilder criteria are not supported by ArrayDataProvider.',
        ];

        yield 'a ColumnControl search' => [
            ['columns' => [['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'columnControl' => ['search' => ['value' => 'Alice', 'logic' => 'contains', 'type' => 'text']]]]],
            'ColumnControl searches are not supported by ArrayDataProvider.',
        ];

        yield 'a configured filter value' => [
            ['filters' => ['status' => 'active']],
            'Configured filters are not supported by ArrayDataProvider.',
        ];
    }

    #[Test]
    public function it_ignores_the_query_string_of_a_page_load(): void
    {
        $table = self::table(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));
        $call  = self::buildRuntime()->handle($table, new Request(query: ['filters' => ['status' => 'active']]));

        $result = $call->fetchData(new DataTableRequest(draw: 1, columns: new Columns([])));

        $this->assertSame(0, $result->recordsTotal);
    }

    #[Test]
    public function it_checks_the_request_fetch_data_serves(): void
    {
        $table = self::table(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));

        $this->expectException(BadRequestHttpException::class);

        self::buildRuntime()->open($table)->fetchData(new DataTableRequest(draw: 1, columns: new Columns([]), filters: ['status' => 'active']));
    }

    #[Test]
    public function it_checks_the_provider_again_after_a_refusal(): void
    {
        $table = self::table(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));
        $call  = self::buildRuntime()->handle($table, new Request(query: ['draw' => 1, 'filters' => ['status' => 'active']]));

        try {
            $call->dataProvider();
            $this->fail('The first call must refuse the request.');
        } catch (BadRequestHttpException) {
        }

        $this->expectException(BadRequestHttpException::class);

        $call->dataProvider();
    }

    #[Test]
    public function it_accepts_empty_filter_values(): void
    {
        $table = self::table(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));
        $call  = self::buildRuntime()->handle($table, new Request(query: ['draw' => 1, 'filters' => ['status' => '']]));

        $this->assertInstanceOf(ArrayDataProvider::class, $call->dataProvider());
    }

    #[Test]
    public function it_does_not_ask_an_exhaustive_supports_about_the_row_scope_features(): void
    {
        $provider = new class implements DataProviderInterface {
            public function iterateRows(DataTableRequest $request): iterable
            {
                return $this->fetchData($request)->data;
            }

            public function supports(DataSourceFeature $feature): bool
            {
                return match ($feature) {
                    DataSourceFeature::SearchBuilder, DataSourceFeature::ColumnControl, DataSourceFeature::ConfiguredFilters, DataSourceFeature::Summaries => true,
                };
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
            }
        };

        $call = self::buildRuntime()->handle(self::table($provider), new Request(query: ['draw' => 1]));

        $this->assertSame([], $this->deprecationsOf(fn () => $this->assertSame($provider, $call->dataProvider())));
    }

    #[Test]
    public function it_treats_a_feature_the_supports_match_does_not_know_as_unsupported(): void
    {
        $provider = new class implements DataProviderInterface {
            public function iterateRows(DataTableRequest $request): iterable
            {
                return $this->fetchData($request)->data;
            }

            public function supports(DataSourceFeature $feature): bool
            {
                return match ($feature) {
                    DataSourceFeature::SearchBuilder, DataSourceFeature::ColumnControl, DataSourceFeature::Summaries => true,
                };
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
            }
        };

        $call = self::buildRuntime()->handle(self::table($provider), new Request(query: ['draw' => 1, 'filters' => ['status' => 'active']]));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Configured filters are not supported by');

        $call->dataProvider();
    }

    #[Test]
    public function it_raises_nothing_for_a_provider_that_declares_every_feature(): void
    {
        $call = self::buildRuntime()->handle(self::table(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns())), new Request(query: ['draw' => 1]));

        $this->assertSame([], $this->deprecationsOf(static fn () => $call->dataProvider()));
    }

    #[Test]
    public function it_refuses_summaries_the_provider_cannot_compute(): void
    {
        $provider = new class implements DataProviderInterface {
            public function iterateRows(DataTableRequest $request): iterable
            {
                return $this->fetchData($request)->data;
            }

            public function supports(DataSourceFeature $feature): bool
            {
                return DataSourceFeature::Summaries !== $feature;
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
            }
        };

        $call = self::buildRuntime()->handle(self::table($provider, [NumberColumn::new('price')->summarize(new Sum())]), new Request(query: ['draw' => 1]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Column summaries are not supported by');

        $call->dataProvider();
    }

    #[Test]
    #[DataProvider('mutatingTables')]
    public function it_refuses_actions_on_a_provider_that_cannot_check_the_row_scope(\Closure $configure): void
    {
        foreach ([self::unscopedProvider(), new ExhaustiveMatchScopedProvider(), new ScopedProvider(declared: [])] as $provider) {
            try {
                (new DataSourceFeatureGuard())->assertMutationsScoped($configure(new DataTable('people')), $provider);
                $this->fail('The actions must be refused.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('unscopedMutations()', $exception->getMessage());
                $this->assertStringEndsWith('.', $exception->getMessage());
            }
        }
    }

    #[Test]
    #[DataProvider('mutatingTables')]
    public function it_accepts_actions_on_a_scoped_provider_or_an_opted_out_table(\Closure $configure): void
    {
        $guard = new DataSourceFeatureGuard();

        $guard->assertMutationsScoped($configure(new DataTable('people')), new ScopedProvider(declared: [DataSourceFeature::RowScope]));
        $guard->assertMutationsScoped($configure(new DataTable('people'))->unscopedMutations(), self::unscopedProvider());
        $this->addToAssertionCount(1);
    }

    #[Test]
    #[DataProvider('actionsServedByNoEndpoint')]
    public function it_accepts_link_only_and_empty_actions_on_any_provider(\Closure $configure): void
    {
        (new DataSourceFeatureGuard())->assertMutationsScoped($configure(new DataTable('people')), self::unscopedProvider());
        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{\Closure(DataTable): DataTable}>
     */
    public static function actionsServedByNoEndpoint(): iterable
    {
        yield 'empty actions' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', new Actions())])];
        yield 'link-only detail' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::detail()->linkToUrl('/books')))])];
        yield 'custom action' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::new('open')->linkToUrl('/books')))])];
    }

    #[Test]
    public function it_accepts_a_table_without_actions_on_any_provider(): void
    {
        (new DataSourceFeatureGuard())->assertMutationsScoped((new DataTable('people'))->columns(self::columns()), self::unscopedProvider());
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_refuses_the_first_data_request_of_a_table_with_actions_on_an_unscoped_provider(): void
    {
        $table = self::table(self::unscopedProvider(), [BooleanColumn::new('active')->renderAsSwitch()]);
        $call  = self::buildRuntime()->handle($table, new Request(query: ['draw' => 1]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('unscopedMutations()');

        $call->dataProvider();
    }

    /**
     * @return iterable<string, array{\Closure(DataTable): DataTable}>
     */
    public static function mutatingTables(): iterable
    {
        yield 'row action switch' => [static fn (DataTable $table): DataTable => $table->columns([BooleanColumn::new('active')->renderAsSwitch()])];
        yield 'action column' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete()))])];
        yield 'collapsible detail' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::detail()->collapsible('detail.html.twig')))])];
        yield 'bulk action' => [static fn (DataTable $table): DataTable => $table->setBulkActions((new BulkActions())->add(BulkAction::new('touch')->handler(static fn () => null)))];
    }

    private static function unscopedProvider(): DataProviderInterface
    {
        return new ArrayDataProvider([], new DefaultRowMapper([]), self::columns());
    }

    /**
     * @param list<ColumnInterface>|null $columns
     */
    private static function table(DataProviderInterface $provider, ?array $columns = null): GuardedTable
    {
        return new GuardedTable($provider, $columns ?? self::columns());
    }

    /**
     * @return list<ColumnInterface>
     */
    private static function columns(): array
    {
        return [NumberColumn::new('id'), TextColumn::new('name')];
    }

    private static function optIns(DataSourceMode $mode, ?bool $apiPlatform = null): ResolvedOptIns
    {
        return new ResolvedOptIns(apiPlatform: $apiPlatform, dataSourceMode: $mode);
    }
}

/**
 * @internal
 */
class ScopedProvider implements DataProviderInterface, IdentifierDataProviderInterface
{
    /**
     * @param list<DataSourceFeature> $declared
     */
    public function __construct(private readonly array $declared)
    {
    }

    public function iterateRows(DataTableRequest $request): iterable
    {
        return [];
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
    }

    public function supports(DataSourceFeature $feature): bool
    {
        return \in_array($feature, $this->declared, true);
    }

    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array
    {
        return [];
    }

    public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array
    {
        return $ids;
    }
}

/**
 * Answers through an exhaustive match that does not know the row scope.
 *
 * @internal
 */
final class ExhaustiveMatchScopedProvider extends ScopedProvider
{
    public function __construct()
    {
        parent::__construct([]);
    }

    public function supports(DataSourceFeature $feature): bool
    {
        return match ($feature) {
            DataSourceFeature::SearchBuilder => true,
        };
    }
}

final class GuardedTable extends AbstractDataTable
{
    /**
     * @param list<ColumnInterface> $columns
     */
    public function __construct(
        private readonly DataProviderInterface $provider,
        private readonly array $columns,
    ) {
    }

    public function configureColumns(): iterable
    {
        return $this->columns;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(TextFilter::new('status'));
    }

    public function createDataProvider(): DataProviderInterface
    {
        return $this->provider;
    }
}
