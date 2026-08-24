<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Profiler;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DataTableProfiler::class)]
final class DataTableProfilerTest extends TestCase
{
    #[Test]
    public function it_collects_rendered_tables(): void
    {
        $profiler = new DataTableProfiler();
        $profiler->collectRenderedTable('App\\ProductDataTable', new DataTable('products'));

        $this->assertSame([[
            'id'                       => 'products',
            'class'                    => 'App\\ProductDataTable',
            'entityClass'              => null,
            'serverSide'               => false,
            'columnCount'              => 0,
            'extensions'               => [],
            'ajax'                     => null,
            'hasData'                  => false,
            'rowCount'                 => 0,
            'dataController'           => null,
            'forwardedQueryParameters' => [],
            'columns'                  => [],
            'deniedColumns'            => [],
            'filters'                  => [],
            'mercure'                  => null,
            'editModal'                => ['template' => null, 'adapter' => null],
        ]], $profiler->getRenderedTables());
    }

    #[Test]
    public function it_collects_every_extension_including_layout_aware_ones(): void
    {
        $table = new DataTable('products');
        $table->getExtensionsCollection()
            ->addButtonsExtension([ButtonType::CSV])
            ->addSelectExtension(static fn (SelectExtension $extension) => $extension->withCheckbox());

        $profiler = new DataTableProfiler();
        $profiler->collectRenderedTable('App\\ProductDataTable', $table);

        $extensions = $profiler->getRenderedTables()[0]['extensions'];

        $this->assertSame(['buttons', 'select'], array_column($extensions, 'key'));

        $buttons = $extensions[0];
        $this->assertTrue($buttons['layoutAware'], 'Buttons is layout aware and absent from the client payload.');
        $this->assertNotEmpty($buttons['options'], 'Extension options must reach the profiler.');

        $select = $extensions[1];
        $this->assertFalse($select['layoutAware']);
        $this->assertTrue($select['options']['withCheckbox']);
    }

    #[Test]
    public function it_collects_columns_filters_and_denied_columns(): void
    {
        $name   = TextColumn::new('name');
        $secret = TextColumn::new('secret');

        $table = new DataTable('products');
        $table->columns([$name, $secret]);
        $table->setFilters((new Filters())->add(TextFilter::new('name')));
        $table->data([['name' => 'Foo', 'secret' => 'hidden']]);

        $profiler = new DataTableProfiler();
        $profiler->collectRenderedTable('App\\ProductDataTable', $table, [
            'entityClass'     => 'App\\Entity\\Product',
            'originalColumns' => [$name, $secret],
            'allowedColumns'  => [$name],
        ]);

        $record = $profiler->getRenderedTables()[0];

        $this->assertSame('App\\Entity\\Product', $record['entityClass']);
        $this->assertSame(['name', 'secret'], array_column($record['columns'], 'name'));
        $this->assertSame(['secret'], $record['deniedColumns']);
        $this->assertSame(['name'], array_column($record['filters'], 'name'));
        $this->assertSame(1, $record['rowCount']);
    }

    #[Test]
    public function it_collects_ajax_queries(): void
    {
        $profiler = new DataTableProfiler();
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', null, 42, 10, 1.5);

        $this->assertSame([[
            'class'           => 'App\\ProductDataTable',
            'token'           => 'token',
            'request'         => null,
            'requestSummary'  => null,
            'recordsTotal'    => 42,
            'recordsFiltered' => 10,
            'durationMs'      => 1.5,
            'providerClass'   => null,
            'entityClass'     => null,
            'rowCount'        => 0,
            'payloadBytes'    => 0,
            'httpStatus'      => null,
        ]], $profiler->getAjaxQueries());
    }

    #[Test]
    public function it_flattens_the_ajax_request_into_scalars(): void
    {
        $request = DataTableRequest::fromRequest(Request::create('/datatables', 'GET', [
            'draw'    => '3',
            'start'   => '20',
            'length'  => '10',
            'search'  => ['value' => 'foo', 'regex' => 'false'],
            'order'   => [['column' => '1', 'dir' => 'desc']],
            'columns' => [
                ['data' => '0', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true'],
                ['data' => '1', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => 'bar', 'regex' => 'false']],
            ],
            'filters' => ['status' => 'active'],
        ]));

        $profiler = new DataTableProfiler();
        $profiler->collectAjaxQuery(
            class: 'App\\ProductDataTable',
            token: 'token',
            request: $request,
            recordsTotal: 100,
            recordsFiltered: 25,
            durationMs: 1.5,
            providerClass: 'App\\Provider',
            entityClass: 'App\\Entity\\Product',
            rowCount: 10,
            payloadBytes: 2048,
            httpStatus: 200,
        );

        $summary = $profiler->getAjaxQueries()[0]['requestSummary'];

        $this->assertSame(3, $summary['draw']);
        $this->assertSame(3, $summary['page']);
        $this->assertSame(['value' => 'foo', 'regex' => false], $summary['search']);
        $this->assertSame([['column' => 1, 'name' => 'name', 'dir' => 'desc']], $summary['order']);
        $this->assertSame(['status' => 'active'], $summary['filters']);
        $this->assertSame([
            ['index' => 0, 'data' => '0', 'name' => 'id', 'searchable' => false, 'orderable' => true, 'searchValue' => null, 'columnControl' => null],
            ['index' => 1, 'data' => '1', 'name' => 'name', 'searchable' => true, 'orderable' => true, 'searchValue' => 'bar', 'columnControl' => null],
        ], $summary['columns']);
    }

    /**
     * ColumnControl submits its own scalar search (value/logic/type) independently of the
     * plain column search box above -- summarizeRequestColumns() used to read only
     * $column->search, silently dropping this even though it drives real query filtering
     * via ColumnControlSearchFilter.
     */
    #[Test]
    public function it_summarizes_a_column_controls_scalar_search(): void
    {
        $request = DataTableRequest::fromRequest(Request::create('/datatables', 'GET', [
            'draw'    => '1',
            'columns' => [
                [
                    'data'          => '0', 'name' => 'status', 'searchable' => 'true', 'orderable' => 'true',
                    'columnControl' => ['search' => ['value' => 'active', 'logic' => 'equal', 'type' => 'text']],
                ],
            ],
        ]));

        $profiler = new DataTableProfiler();
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', $request, 10, 10, 1.0);

        $summary = $profiler->getAjaxQueries()[0]['requestSummary'];

        $this->assertSame([
            'value' => 'active',
            'logic' => 'equal',
            'type'  => 'text',
            'list'  => [],
        ], $summary['columns'][0]['columnControl']);
    }

    /**
     * ColumnControl's searchList (a checkbox list of selected values, e.g. the "Empty"
     * option matching NULL rows) is a second, independent piece of ColumnControl state --
     * also dropped before this, alongside the scalar search.
     */
    #[Test]
    public function it_summarizes_a_column_controls_search_list(): void
    {
        $request = DataTableRequest::fromRequest(Request::create('/datatables', 'GET', [
            'draw'    => '1',
            'columns' => [
                [
                    'data'          => '0', 'name' => 'department', 'searchable' => 'true', 'orderable' => 'true',
                    'columnControl' => ['list' => ['Sales', '']],
                ],
            ],
        ]));

        $profiler = new DataTableProfiler();
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', $request, 10, 10, 1.0);

        $summary = $profiler->getAjaxQueries()[0]['requestSummary'];

        $this->assertSame([
            'value' => null,
            'logic' => null,
            'type'  => null,
            'list'  => ['Sales', ''],
        ], $summary['columns'][0]['columnControl']);
    }

    /**
     * ColumnControl::$list is unvalidated request input -- ColumnControl::fromArray() reads
     * $data['list'] ?? [] verbatim, so a client can submit a nested array for one entry. The
     * panel's join() filter cannot render an array as a string and used to crash the whole
     * profiler panel; every entry must reduce to a safe scalar before it ever reaches Twig.
     */
    #[Test]
    public function it_normalizes_a_non_scalar_search_list_entry_instead_of_forwarding_it_unchanged(): void
    {
        $request = DataTableRequest::fromRequest(Request::create('/datatables', 'GET', [
            'draw'    => '1',
            'columns' => [
                [
                    'data'          => '0', 'name' => 'department', 'searchable' => 'true', 'orderable' => 'true',
                    'columnControl' => ['list' => ['Sales', ['nested' => 'value']]],
                ],
            ],
        ]));

        $profiler = new DataTableProfiler();
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', $request, 10, 10, 1.0);

        $summary = $profiler->getAjaxQueries()[0]['requestSummary'];

        $this->assertSame(
            ['Sales', '(invalid array value)'],
            $summary['columns'][0]['columnControl']['list'],
        );
    }

    #[Test]
    public function it_omits_column_control_when_the_column_declares_it_without_a_search_or_list(): void
    {
        $request = DataTableRequest::fromRequest(Request::create('/datatables', 'GET', [
            'draw'    => '1',
            'columns' => [
                [
                    'data'          => '0', 'name' => 'status', 'searchable' => 'true', 'orderable' => 'true',
                    'columnControl' => [],
                ],
            ],
        ]));

        $profiler = new DataTableProfiler();
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', $request, 10, 10, 1.0);

        $summary = $profiler->getAjaxQueries()[0]['requestSummary'];

        $this->assertNull($summary['columns'][0]['columnControl']);
    }

    #[Test]
    public function reset_clears_all_collected_state(): void
    {
        $profiler = new DataTableProfiler();
        $profiler->collectRenderedTable('App\\ProductDataTable', new DataTable('products'));
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', null, 1, 1, 0.1);

        $profiler->reset();

        $this->assertSame([], $profiler->getRenderedTables());
        $this->assertSame([], $profiler->getAjaxQueries());
    }
}
