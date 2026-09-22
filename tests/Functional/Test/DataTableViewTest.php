<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessClientSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessUnregisteredDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class DataTableViewTest extends DataTableTestCase
{
    public function test_it_reads_the_only_table_on_the_page(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/books');

        $this->dataTableView($crawler)
            ->assertRowCount(3)
            ->assertColumns(['id', 'title'])
            ->assertColumnValues('title', ['Symfony 7', 'UX in Action', 'Escaping &quot;quoted&quot; & <tagged> titles'])
            ->assertOption('pageLength', 25);
    }

    public function test_it_selects_a_table_by_class_when_the_page_has_several(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/two-tables');

        $this->dataTableView($crawler, HarnessClientSideDataTable::class)->assertRowCount(3);
    }

    /**
     * The attribute is HTML-encoded on the way out and decoded by the HTML parser on the way in.
     * Decoding it a second time would corrupt a value that legitimately contains an entity.
     */
    public function test_it_keeps_entity_like_values_untouched(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/books');

        $this->assertSame(
            'Escaping &quot;quoted&quot; & <tagged> titles',
            $this->dataTableView($crawler)->row(2)['title'],
        );
    }

    public function test_it_refuses_an_ambiguous_page(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/two-tables');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/found 2/');

        $this->dataTableView($crawler);
    }

    public function test_a_server_side_table_embeds_no_rows(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/two-tables');

        $this->assertSame([], $this->dataTableView($crawler, HarnessServerSideDataTable::class)->rows());
    }

    public function test_an_unregistered_class_cannot_be_selected(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/two-tables');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/HarnessUnregisteredDataTable/');

        $this->dataTableView($crawler, HarnessUnregisteredDataTable::class);
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
