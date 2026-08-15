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

        $this->assertSame([[
            'id'          => 'products',
            'class'       => 'App\\ProductDataTable',
            'serverSide'  => false,
            'columnCount' => 0,
            'extensions'  => [],
            'ajax'        => null,
            'hasData'     => false,
        ]], $profiler->getRenderedTables());
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
            'recordsTotal'    => 42,
            'recordsFiltered' => 10,
            'durationMs'      => 1.5,
        ]], $profiler->getAjaxQueries());
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
