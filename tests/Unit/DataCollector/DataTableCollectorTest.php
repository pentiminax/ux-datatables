<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataCollector;

use Pentiminax\UX\DataTables\DataCollector\DataTableCollector;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(DataTableCollector::class)]
final class DataTableCollectorTest extends TestCase
{
    #[Test]
    public function it_returns_the_correct_collector_name(): void
    {
        $collector = new DataTableCollector(new DataTableProfiler());

        $this->assertSame('datatables', $collector->getName());
    }

    #[Test]
    public function it_collects_rendered_tables_and_ajax_queries_from_profiler(): void
    {
        $profiler = new DataTableProfiler();
        $profiler->collectRenderedTable('App\\ProductDataTable', new DataTable('products'));
        $profiler->collectAjaxQuery('App\\ProductDataTable', 'token', null, 42, 10, 1.5);

        $collector = new DataTableCollector($profiler);
        $collector->collect(Request::create('/'), new Response());

        $this->assertSame(1, $collector->getTableCount());
        $this->assertSame(1, $collector->getQueryCount());
        $this->assertCount(1, $collector->getTables());
        $this->assertCount(1, $collector->getQueries());
        $this->assertSame('products', $collector->getTables()[0]['id']);
        $this->assertSame(42, $collector->getQueries()[0]['recordsTotal']);
    }
}
