<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataCollector;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataCollector\DataTableCollector;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Tests\Kernel\ProfilerPanelAppKernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * @internal
 */
final class DataTablePanelRenderTest extends TestCase
{
    #[Test]
    public function the_panel_renders_every_collected_section(): void
    {
        $kernel = new ProfilerPanelAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableProfiler $profiler */
        $profiler = $container->get('test.datatables.profiler');
        $profiler->collectRenderedTable('App\\ProductDataTable', $this->createTable(), [
            'entityClass'     => 'App\\Entity\\Product',
            'originalColumns' => [TextColumn::new('name'), TextColumn::new('secret')],
            'allowedColumns'  => [TextColumn::new('name')],
        ]);
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
            providerClass: 'App\\Provider',
            entityClass: 'App\\Entity\\Product',
            rowCount: 10,
            payloadBytes: 2048,
            httpStatus: 200,
        );

        /** @var DataTableCollector $collector */
        $collector = $container->get('test.datatables.data_collector');
        $collector->collect(Request::create('/'), new Response());

        /** @var Environment $twig */
        $twig  = $container->get('test.twig');
        $panel = $twig->load('@DataTables/Collector/data_collector.html.twig')
            ->renderBlock('panel', ['collector' => $collector]);

        $this->assertStringContainsString('Extensions', $panel);
        $this->assertStringContainsString('buttons', $panel);
        $this->assertStringContainsString('responsive', $panel);
        $this->assertStringContainsString('Removed by static permission filtering', $panel);
        $this->assertStringContainsString('Request columns', $panel);
        $this->assertStringContainsString('2048 B', $panel);
        $this->assertStringNotContainsString('Sensitive-Row-Value', $panel);
    }

    private function createTable(): DataTable
    {
        $table = new DataTable('products');
        $table->columns([TextColumn::new('name')]);
        $table->data([['name' => 'Sensitive-Row-Value']]);
        $table->setFilters((new Filters())->add(TextFilter::new('name')));
        $table->getExtensionsCollection()->addButtonsExtension([ButtonType::CSV]);
        // Options serialize to `true`, which is not countable: the panel must
        // dump them without testing their emptiness.
        $table->getExtensionsCollection()->addResponsiveExtension();

        return $table;
    }
}
