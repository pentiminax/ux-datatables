<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Profiler;

use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        $tables = $profiler->getRenderedTables();
        $this->assertCount(1, $tables);
        $this->assertSame('products', $tables[0]['id']);
        $this->assertSame('App\\ProductDataTable', $tables[0]['class']);
        $this->assertArrayHasKey('serverSide', $tables[0]);
        $this->assertArrayHasKey('columnCount', $tables[0]);
        $this->assertArrayHasKey('extensions', $tables[0]);
    }

    #[Test]
    public function it_collects_ajax_queries(): void
    {
        $profiler = new DataTableProfiler();
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', null, 42, 10, 1.5);

        $queries = $profiler->getAjaxQueries();
        $this->assertCount(1, $queries);
        $this->assertSame('token', $queries[0]['token']);
        $this->assertSame(42, $queries[0]['recordsTotal']);
        $this->assertSame(10, $queries[0]['recordsFiltered']);
        $this->assertSame(1.5, $queries[0]['durationMs']);
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
