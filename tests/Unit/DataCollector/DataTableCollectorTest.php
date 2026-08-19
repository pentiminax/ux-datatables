<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataCollector;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataCollector\DataTableCollector;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @internal
 */
#[CoversClass(DataTableCollector::class)]
final class DataTableCollectorTest extends TestCase
{
    #[Test]
    public function it_collects_rendered_tables_and_ajax_queries_from_profiler(): void
    {
        $profiler = new DataTableProfiler();
        $profiler->collectRenderedTable('App\\ProductDataTable', new DataTable('products'));
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', null, 42, 10, 1.5);

        $collector = new DataTableCollector($profiler);
        $collector->collect(Request::create('/'), new Response());

        $this->assertSame('datatables', $collector->getName());
        $this->assertSame(1, $collector->getTableCount());
        $this->assertSame(1, $collector->getQueryCount());
        $this->assertCount(1, $collector->getTables());
        $this->assertCount(1, $collector->getQueries());
        $this->assertSame('products', $collector->getTables()[0]['id']);
        $this->assertSame(42, $collector->getQueries()[0]['recordsTotal']);
    }

    #[Test]
    public function it_keeps_the_request_summary_flattened_to_scalars(): void
    {
        $profiler = new DataTableProfiler();
        $profiler->collectAjaxQuery(
            class: 'App\\ProductDataTable',
            token: 'token',
            request: DataTableRequest::fromRequest(Request::create('/datatables', 'GET', [
                'draw'    => '2',
                'start'   => '10',
                'length'  => '10',
                'search'  => ['value' => 'foo', 'regex' => 'false'],
                'order'   => [['column' => '0', 'dir' => 'asc']],
                'columns' => [['data' => '0', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true']],
                'filters' => ['status' => 'active'],
            ])),
            recordsTotal: 42,
            recordsFiltered: 10,
            durationMs: 1.5,
        );

        $collector = new DataTableCollector($profiler);
        $collector->collect(Request::create('/'), new Response());

        $summary = $collector->getQueries()[0]['requestSummary'];

        // Everything the panel renders as a table must survive as a scalar, so
        // the template never depends on expanding a dump node.
        $this->assertSame(2, $summary['draw']);
        $this->assertSame(2, $summary['page']);
        $this->assertSame('foo', $summary['search']['value']);
        $this->assertSame([['column' => 0, 'name' => 'name', 'dir' => 'asc']], $summary['order']);
        $this->assertSame('name', $summary['columns'][0]['name']);
        $this->assertTrue($summary['columns'][0]['searchable']);

        // Submitted filter values stay a dump: they are arbitrary user input.
        $this->assertInstanceOf(Data::class, $summary['filters']);
    }

    #[Test]
    public function it_never_copies_row_values_into_the_profile(): void
    {
        $table = new DataTable('products');
        $table->columns([TextColumn::new('name')]);
        $table->data([['name' => 'Sensitive-Row-Value']]);

        $profiler = new DataTableProfiler();
        $profiler->collectRenderedTable('App\\ProductDataTable', $table);
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', null, 1, 1, 0.5, rowCount: 1, payloadBytes: 128);

        $collector = new DataTableCollector($profiler);
        $collector->collect(Request::create('/'), new Response());

        $this->assertStringNotContainsString('Sensitive-Row-Value', serialize($collector->getTables()));
        $this->assertSame(1, $collector->getTables()[0]['rowCount']);
        $this->assertSame(1, $collector->getQueries()[0]['rowCount']);
        $this->assertSame(128, $collector->getQueries()[0]['payloadBytes']);
    }
}
