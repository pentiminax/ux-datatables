<?php

namespace Pentiminax\UX\DataTables\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Pentiminax\UX\DataTables\Builder\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;

class DataTablesExtensionTest extends TestCase
{
    public function testRenderDataTable(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableBuilderInterface $builder */
        $builder = $container->get('test.datatables.builder');

        $table = $builder->createDataTable('table');

        $table->setOptions([
            'columns' => [
                ['title' => 'Column 1'],
                ['title' => 'Column 2'],
            ],
            'data' => [
                ['Row 1 Column 1', 'Row 1 Column 2'],
                ['Row 2 Column 1', 'Row 2 Column 2'],
            ],
        ]);

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable(
            $table,
            ['data-controller' => 'mycontroller', 'class' => 'myclass']
        );

        $this->assertSame(
            expected: '<table id="table" data-controller="mycontroller pentiminax--ux-datatables--datatable" data-pentiminax--ux-datatables--datatable-view-value="{&quot;columns&quot;:[{&quot;title&quot;:&quot;Column 1&quot;},{&quot;title&quot;:&quot;Column 2&quot;}],&quot;data&quot;:[[&quot;Row 1 Column 1&quot;,&quot;Row 1 Column 2&quot;],[&quot;Row 2 Column 1&quot;,&quot;Row 2 Column 2&quot;]]}" class="myclass"></table>',
            actual: $rendered
        );
    }
}
