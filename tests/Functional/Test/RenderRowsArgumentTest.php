<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class RenderRowsArgumentTest extends DataTableTestCase
{
    public function test_the_rows_argument_replaces_the_configured_rows(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/rows', ['rows' => $this->json([['id' => 7, 'title' => 'Dune']])]);

        $this->dataTableView($crawler)->assertRowCount(1)->assertColumnValues('title', ['Dune']);
    }

    public function test_the_positional_form_passes_the_rows_after_the_attributes(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/rows-positional', ['rows' => $this->json([['id' => 7, 'title' => 'Dune']])]);

        $this->dataTableView($crawler)->assertRowCount(1)->assertColumnValues('title', ['Dune']);
    }

    public function test_a_second_request_on_the_same_kernel_carries_only_its_own_rows(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $client->request('GET', '/rows', ['rows' => $this->json([['id' => 1, 'title' => 'First user']])]);
        $crawler = $client->request('GET', '/rows', ['rows' => $this->json([['id' => 2, 'title' => 'Second user']])]);

        $this->dataTableView($crawler)->assertRowCount(1)->assertColumnValues('title', ['Second user']);
    }

    public function test_a_request_without_rows_after_one_with_rows_renders_the_configured_source(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $client->request('GET', '/rows', ['rows' => $this->json([['id' => 1, 'title' => 'First user']])]);
        $crawler = $client->request('GET', '/rows');

        $this->dataTableView($crawler)->assertRowCount(3)->assertColumnValues('title', ['Symfony 7', 'UX in Action', 'Escaping &quot;quoted&quot; & <tagged> titles']);
    }

    public function test_an_empty_array_renders_an_empty_table_instead_of_the_configured_source(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $crawler = $client->request('GET', '/hydrated-rows');
        $this->dataTableView($crawler)->assertRowCount(2);

        $crawler = $client->request('GET', '/hydrated-rows', ['rows' => '[]']);
        $this->dataTableView($crawler)->assertRowCount(0);

        $crawler = $client->request('GET', '/hydrated-rows');
        $this->dataTableView($crawler)->assertRowCount(2);
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function json(array $rows): string
    {
        return json_encode($rows, \JSON_THROW_ON_ERROR);
    }
}
