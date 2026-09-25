<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\PrefixedHarnessAppKernel;
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

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new PrefixedHarnessAppKernel('test', true);
    }
}
