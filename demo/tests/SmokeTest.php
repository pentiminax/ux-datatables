<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataTable\BulkOrdersDataTable;
use App\DataTable\CustomersDataTable;
use App\DataTable\FilteredOrdersDataTable;
use App\DataTable\LayoutOrdersDataTable;
use App\DataTable\OrdersDataTable;
use App\DataTable\ProductActionsDataTable;
use App\Demo\DemoSeeder;
use App\Demo\Pages;
use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Keeps the demo honest against the bundle on the same branch: every page renders its table, and
 * every server-side table answers the Ajax endpoint with the seeded rows.
 *
 * @internal
 */
final class SmokeTest extends DataTableTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::getContainer()->get(DemoSeeder::class)->reset();
        self::ensureKernelShutdown();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pages(): iterable
    {
        foreach (Pages::all() as $page) {
            foreach (['en', 'fr'] as $locale) {
                yield $page->route.' '.$locale => [$page->route, $locale];
            }
        }
    }

    #[DataProvider('pages')]
    public function test_page_renders_its_table(string $route, string $locale): void
    {
        $client = self::createClient();
        $url    = self::getContainer()->get('router')->generate($route, ['_locale' => $locale]);

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('table[data-controller~="pentiminax--ux-datatables--datatable"]'));
        self::assertGreaterThanOrEqual(3, $crawler->filter('.code-panel [role="tab"]')->count());
    }

    public function test_choice_labels_follow_the_request_locale(): void
    {
        $client = self::createClient();

        foreach (['en' => 'Delivered', 'fr' => 'Livrée'] as $locale => $label) {
            $url = self::getContainer()->get('router')->generate('server_side', ['_locale' => $locale]);

            $client->request('GET', $url);

            self::assertStringContainsString(json_encode($label, \JSON_HEX_QUOT | \JSON_HEX_APOS), (string) html_entity_decode($client->getResponse()->getContent()));
        }
    }

    /**
     * @return iterable<string, array{class-string, int}>
     */
    public static function serverSideTables(): iterable
    {
        yield 'orders' => [OrdersDataTable::class, DemoSeeder::ORDERS];
        yield 'filtered orders' => [FilteredOrdersDataTable::class, DemoSeeder::ORDERS];
        yield 'bulk orders' => [BulkOrdersDataTable::class, DemoSeeder::ORDERS];
        yield 'scrolling orders' => [LayoutOrdersDataTable::class, DemoSeeder::ORDERS];
        yield 'customers' => [CustomersDataTable::class, DemoSeeder::CUSTOMERS];
        yield 'products' => [ProductActionsDataTable::class, 60];
    }

    /**
     * @param class-string<\Pentiminax\UX\DataTables\Model\AbstractDataTable> $table
     */
    #[DataProvider('serverSideTables')]
    public function test_server_side_table_serves_seeded_rows(string $table, int $total): void
    {
        self::createClient();

        $this->dataTable($table)->length(10)->fetch()
            ->assertRecordsTotal($total)
            ->assertRowCount(10);
    }

    public function test_ternary_filter_splits_orders(): void
    {
        self::createClient();

        $express  = $this->dataTable(FilteredOrdersDataTable::class)->filter('express', '1')->fetch()->recordsFiltered();
        $standard = $this->dataTable(FilteredOrdersDataTable::class)->filter('express', '0')->fetch()->recordsFiltered();

        self::assertGreaterThan(0, $express);
        self::assertGreaterThan(0, $standard);
        self::assertSame(DemoSeeder::ORDERS, $express + $standard);
    }
}
