<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Test;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Base class for testing the tables an application builds with this bundle.
 *
 * Use {@see self::dataTable()} for a server-side table: it sends a real request to the bundle's
 * Ajax route, so the router, the firewall, the controller, the query pipeline, and the data
 * provider all take part. Use {@see self::dataTableView()} for a client-side table, whose rows
 * travel inside the page the application rendered.
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
        return new DataTableRequestBuilder(
            $this->dataTableClient(),
            $this->dataTableRegistry(),
            $this->dataTableUrlGenerator(),
            $dataTableClass,
        );
    }

    /**
     * Reads a client-side table out of a page the application rendered.
     *
     * @param class-string<AbstractDataTable>|null $dataTableClass required when the page renders
     *                                                             more than one table
     */
    protected function dataTableView(Crawler $crawler, ?string $dataTableClass = null): DataTableView
    {
        $actionToken = null;

        if (null !== $dataTableClass) {
            $actionToken = $this->dataTableRegistry()->getActionToken($dataTableClass);

            if (null === $actionToken) {
                throw new \LogicException(\sprintf('DataTable "%s" is not registered. Add the #[AsDataTable] attribute or register it as a service in the kernel under test.', $dataTableClass));
            }
        }

        return DataTableView::fromCrawler($crawler, $actionToken);
    }

    final protected function dataTableRegistry(): AjaxDataTableRegistry
    {
        $registry = static::getContainer()->get('datatables.ajax.registry');

        if (!$registry instanceof AjaxDataTableRegistry) {
            throw new \LogicException('The DataTables bundle is not registered in the kernel under test.');
        }

        return $registry;
    }

    final protected function dataTableUrlGenerator(): UrlGeneratorInterface
    {
        $router = static::getContainer()->get('router');

        if (!$router instanceof UrlGeneratorInterface) {
            throw new \LogicException('The router is not available in the kernel under test.');
        }

        return $router;
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
