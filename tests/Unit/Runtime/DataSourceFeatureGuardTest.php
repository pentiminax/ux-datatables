<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\FeatureAwareDataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Filter\TextFilter;
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
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
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
    #[Test]
    #[DataProvider('apiPlatformRefusals')]
    public function it_refuses_at_render_what_an_api_platform_collection_cannot_apply(\Closure $configure, string $message): void
    {
        $table = $configure(new DataTable('books'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        (new DataSourceFeatureGuard())->assertConfigurable($table, DataSourceMode::ApiPlatform);
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
                ->columns([TextColumn::new('title')->setColumnControl(['searchList'])]),
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

        (new DataSourceFeatureGuard())->assertConfigurable($table, $mode);
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

        (new DataSourceFeatureGuard())->assertConfigurable($configure(new DataTable('books')), $mode);
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
        $runtime = $this->runtime(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($message);

        $runtime->handleRequest(new Request(query: ['draw' => 1, ...$query]));
        $runtime->getDataProvider();
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
    public function it_refuses_a_request_handled_after_the_provider_was_resolved(): void
    {
        $runtime = $this->runtime(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));
        $runtime->getDataProvider();

        $this->expectException(BadRequestHttpException::class);

        $runtime->handleRequest(new Request(query: ['draw' => 1, 'filters' => ['status' => 'active']]));
    }

    #[Test]
    public function it_ignores_the_query_string_of_a_page_load(): void
    {
        $runtime = $this->runtime(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));
        $runtime->handleRequest(new Request(query: ['filters' => ['status' => 'active']]));

        $result = $runtime->fetchData(new DataTableRequest(draw: 1, columns: new Columns([])));

        $this->assertSame(0, $result->recordsTotal);
    }

    #[Test]
    public function it_checks_the_request_fetch_data_serves(): void
    {
        $runtime = $this->runtime(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));

        $this->expectException(BadRequestHttpException::class);

        $runtime->fetchData(new DataTableRequest(draw: 1, columns: new Columns([]), filters: ['status' => 'active']));
    }

    #[Test]
    public function it_checks_the_provider_again_after_a_refusal(): void
    {
        $runtime = $this->runtime(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));
        $runtime->handleRequest(new Request(query: ['draw' => 1, 'filters' => ['status' => 'active']]));

        try {
            $runtime->getDataProvider();
            $this->fail('The first call must refuse the request.');
        } catch (BadRequestHttpException) {
        }

        $this->expectException(BadRequestHttpException::class);

        $runtime->getDataProvider();
    }

    #[Test]
    public function it_accepts_empty_filter_values(): void
    {
        $runtime = $this->runtime(new ArrayDataProvider([], new DefaultRowMapper([]), self::columns()));
        $runtime->handleRequest(new Request(query: ['draw' => 1, 'filters' => ['status' => '']]));

        $this->assertInstanceOf(ArrayDataProvider::class, $runtime->getDataProvider());
    }

    #[Test]
    public function it_assumes_a_provider_that_declares_nothing_supports_every_feature(): void
    {
        $provider = new class implements DataProviderInterface {
            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
            }
        };

        $runtime = $this->runtime($provider);
        $runtime->handleRequest(new Request(query: ['draw' => 1, 'filters' => ['status' => 'active']]));

        $this->assertSame($provider, $runtime->getDataProvider());
    }

    #[Test]
    public function it_refuses_summaries_the_provider_cannot_compute(): void
    {
        $provider = new class implements DataProviderInterface, FeatureAwareDataProviderInterface {
            public function supports(DataSourceFeature $feature): bool
            {
                return DataSourceFeature::Summaries !== $feature;
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
            }
        };

        $runtime = $this->runtime($provider, [NumberColumn::new('price')->summarize(new Sum())]);
        $runtime->handleRequest(new Request(query: ['draw' => 1]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Column summaries are not supported by');

        $runtime->getDataProvider();
    }

    /**
     * @param list<ColumnInterface>|null $columns
     */
    private function runtime(DataProviderInterface $provider, ?array $columns = null): DataTableRuntime
    {
        $columns ??= self::columns();
        $table = (new DataTable('people'))
            ->columns($columns)
            ->setFilters((new Filters())->add(TextFilter::new('status')));

        return new DataTableRuntime(
            table: $table,
            dataProviderFactory: static fn (): DataProviderInterface => $provider,
            columns: $columns,
        );
    }

    /**
     * @return list<ColumnInterface>
     */
    private static function columns(): array
    {
        return [NumberColumn::new('id'), TextColumn::new('name')];
    }
}
