<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataCollector;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataCollector\DataTableCollector;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Tests\Kernel\ProfilerAppKernel;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final class DataTableCollectorIntegrationTest extends TestCase
{
    #[Test]
    public function collector_is_registered_and_collects_rendered_tables(): void
    {
        $kernel = new ProfilerAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $table = new ConfigurableDataTable(
            [TextColumn::new('name')],
            configureTable: static fn (DataTable $table): DataTable => $table->data([['name' => 'Foo']]),
        );
        $table->setDataTableInfrastructure($container->get('test.datatables.infrastructure'));

        /** @var DataTablesExtension $extension */
        $extension = $container->get('test.datatables.twig_extension');
        $extension->renderDataTable($table);

        /** @var DataTableProfiler $profiler */
        $profiler = $container->get('test.datatables.profiler');
        $this->assertCount(1, $profiler->getRenderedTables(), 'The rendered table should be collected via the wired profiler.');

        /** @var DataTableCollector $collector */
        $collector = $container->get('test.datatables.data_collector');
        $this->assertSame('datatables', $collector->getName());

        $collector->collect(Request::create('/'), new Response());
        $this->assertSame(1, $collector->getTableCount());
        $this->assertSame('ConfigurableDataTable', $collector->getTables()[0]['id']);
    }
}
