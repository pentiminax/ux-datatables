<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Test;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for testing the tables an application builds with this bundle.
 *
 * Use {@see self::dataTable()} for a server-side table: it sends a real request to the bundle's
 * Ajax route, so the router, the firewall, the controller, the query pipeline, and the data
 * provider all take part.
 *
 * DataTables.net itself never runs here. What these helpers assert is what leaves PHP.
 */
abstract class DataTableTestCase extends WebTestCase
{
    /**
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    protected function dataTable(string $dataTableClass): DataTableRequestBuilder
    {
        return new DataTableRequestBuilder($this->dataTableClient(), $this->dataTableRegistry(), $dataTableClass);
    }

    final protected function dataTableRegistry(): AjaxDataTableRegistry
    {
        $registry = static::getContainer()->get('datatables.ajax.registry');

        if (!$registry instanceof AjaxDataTableRegistry) {
            throw new \LogicException('The DataTables bundle is not registered in the kernel under test.');
        }

        return $registry;
    }

    final protected function dataTableClient(): KernelBrowser
    {
        $client = static::getClient();

        if (!$client instanceof KernelBrowser) {
            throw new \LogicException('No client was created. Call static::createClient() before using the DataTable test helpers.');
        }

        return $client;
    }
}
