<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Twig;

use Pentiminax\UX\DataTables\Builder\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use PHPUnit\Framework\TestCase;

class DataTablesExtensionTest extends TestCase
{
    public function testRenderDataTable(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableBuilderInterface $builder */
        $builder = $container->get('test.datatables.builder');

        $table = $builder
            ->createDataTable('table')
            ->lengthMenu([10, 25, 50, 100])
            ->pageLength(25)
        ;

        $table->columns([
            TextColumn::new('firstColumn'),
            TextColumn::new('secondColumn'),
        ]);

        $table->data([
            ['firstColumn' => 'Row 1 Column 1', 'secondColumn' => 'Row 1 Column 2'],
            ['firstColumn' => 'Row 2 Column 1', 'secondColumn' => 'Row 2 Column 2'],
        ]);

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable(
            $table,
            ['data-controller' => 'mycontroller', 'class' => 'myclass']
        );

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $this->assertSame('table', $tableEl->getAttribute('id'));
        $this->assertSame('mycontroller pentiminax--ux-datatables--datatable', $tableEl->getAttribute('data-controller'));
        $this->assertSame('myclass', $tableEl->getAttribute('class'));

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));
        $actual   = json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);

        $expected = [
            'lengthMenu' => [10, 25, 50, 100],
            'pageLength' => 25,
            'columns'    => [
                [
                    'data'       => 'firstColumn',
                    'name'       => 'firstColumn',
                    'orderable'  => true,
                    'searchable' => true,
                    'title'      => 'firstColumn',
                    'type'       => 'string',
                    'visible'    => true,
                ],
                [
                    'data'       => 'secondColumn',
                    'name'       => 'secondColumn',
                    'orderable'  => true,
                    'searchable' => true,
                    'title'      => 'secondColumn',
                    'type'       => 'string',
                    'visible'    => true,
                ],
            ],
            'data' => [
                ['firstColumn' => 'Row 1 Column 1', 'secondColumn' => 'Row 1 Column 2'],
                ['firstColumn' => 'Row 2 Column 1', 'secondColumn' => 'Row 2 Column 2'],
            ],
        ];

        $this->assertSame($expected, $actual);
    }

    public function testRenderDataTableCallsPrepareForRenderingForAbstractDataTable(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $table = new class extends AbstractDataTable {
            public bool $prepareForRenderingCalled = false;

            public function configureColumns(): iterable
            {
                yield TextColumn::new('firstColumn');
            }

            public function prepareForRendering(): void
            {
                $this->prepareForRenderingCalled = true;
            }
        };

        $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $this->assertTrue($table->prepareForRenderingCalled);
    }
}
