<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\PrefixedHarnessAppKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * An application may import the bundle routes under a prefix, so the builder must generate the
 * route instead of assuming /datatables/ajax/data.
 *
 * @internal
 */
final class PrefixedRoutesTest extends DataTableTestCase
{
    public function test_it_reaches_the_ajax_endpoint_behind_a_route_prefix(): void
    {
        static::createClient();

        $this->dataTable(HarnessServerSideDataTable::class)->fetch()->assertRowCount(3);
    }

    public function test_the_hard_coded_path_is_not_the_one_served(): void
    {
        $client = static::createClient();
        $client->request('GET', '/datatables/ajax/data');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function rowActions(): iterable
    {
        yield 'delete' => ['delete', 'DELETE', '/admin/datatables/ajax/delete'];
        yield 'toggle' => ['toggle', 'PATCH', '/admin/datatables/ajax/edit'];
        yield 'detail' => ['detail', 'POST', '/admin/datatables/ajax/detail'];
        yield 'edit form' => ['editForm', 'POST', '/admin/datatables/ajax/edit-form/view'];
        yield 'edit form submit' => ['editFormSubmit', 'POST', '/admin/datatables/ajax/edit-form'];
    }

    #[DataProvider('rowActions')]
    public function test_each_row_action_url_of_the_payload_reaches_its_endpoint(string $name, string $method, string $expectedPath): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/actions');
        $url     = $this->dataTableView($crawler)->option('rowActionUrls')[$name];

        $this->assertSame($expectedPath, $url);

        $client->request($method, $url, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        $this->assertSame(422, $client->getResponse()->getStatusCode());
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new PrefixedHarnessAppKernel('test', true);
    }
}
