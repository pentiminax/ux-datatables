<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class DataTableRequestBuilderTest extends DataTableTestCase
{
    public function test_it_returns_every_row_without_any_criteria(): void
    {
        static::createClient();

        $response = $this->dataTable(HarnessServerSideDataTable::class)->fetch();

        $this->assertSame(3, $response->recordsTotal());
        $this->assertSame(3, $response->recordsFiltered());
        $this->assertCount(3, $response->rows());
        $this->assertSame(1, $response->draw());
    }

    public function test_it_narrows_the_rows_with_a_global_search(): void
    {
        static::createClient();

        $response = $this->dataTable(HarnessServerSideDataTable::class)->search('Symfony')->fetch();

        $this->assertSame(3, $response->recordsTotal());
        $this->assertSame(1, $response->recordsFiltered());
        $this->assertSame('Symfony 7', $response->row(0)['title']);
    }

    public function test_it_orders_rows_by_a_column_name(): void
    {
        static::createClient();

        $response = $this->dataTable(HarnessServerSideDataTable::class)->orderBy('title', 'desc')->fetch();

        $this->assertSame('UX in Action', $response->row(0)['title']);
    }

    public function test_it_pages_with_a_one_indexed_page_number(): void
    {
        static::createClient();

        $response = $this->dataTable(HarnessServerSideDataTable::class)
            ->orderBy('id')
            ->length(2)
            ->page(2)
            ->fetch();

        $this->assertCount(1, $response->rows());
        $this->assertSame(3, $response->recordsFiltered());
    }

    public function test_configuration_methods_do_not_mutate_the_builder(): void
    {
        static::createClient();

        $builder = $this->dataTable(HarnessServerSideDataTable::class);
        $builder->search('Symfony');

        $this->assertSame(3, $builder->fetch()->recordsFiltered());
    }

    public function test_it_exposes_the_raw_response_for_a_missing_table_token(): void
    {
        static::createClient();

        $response = $this->dataTable(HarnessServerSideDataTable::class)
            ->query(['table' => 'not-a-token'])
            ->fetchRaw();

        $this->assertSame(404, $response->getStatusCode());
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
