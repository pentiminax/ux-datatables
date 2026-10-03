<?php

declare(strict_types=1);

namespace App\Demo;

use App\DataTable\BulkOrdersDataTable;
use App\DataTable\ClientSideDataTable;
use App\DataTable\ColumnsDataTable;
use App\DataTable\ColumnToolsDataTable;
use App\DataTable\CustomersDataTable;
use App\DataTable\ExportProductsDataTable;
use App\DataTable\FilteredOrdersDataTable;
use App\DataTable\LayoutOrdersDataTable;
use App\DataTable\OrdersDataTable;
use App\DataTable\ProductActionsDataTable;
use App\DataTable\ShowcaseProductsDataTable;

/**
 * The tour, in reading order. Drives the sidebar, the page headers, the code panel, and the smoke test.
 */
final class Pages
{
    public const string DOCS = 'https://pentiminax.github.io/ux-datatables/';

    /**
     * @return list<Page>
     */
    public static function all(): array
    {
        return [
            new Page('overview', 'start', ShowcaseProductsDataTable::class, 'getting-started/quick-start/', ['ImageColumn', 'MoneyColumn', 'ChoiceColumn', 'renderAsSwitch()', 'Action::edit()', 'Button::excel()', 'responsive()'], ['src/Security/ProductVoter.php']),
            new Page('client_side', 'start', ClientSideDataTable::class, 'guide/client-side-processing/', ['data()', 'pageLength()']),
            new Page('server_side', 'start', OrdersDataTable::class, 'guide/server-side-processing/', ['#[AsDataTable]', 'serverSide()', 'urlState()', 'fixedHeader()']),
            new Page('columns', 'essentials', ColumnsDataTable::class, 'columns/overview/', ['ImageColumn', 'MoneyColumn', 'IconColumn', 'TemplateColumn', 'ChoiceColumn', 'BooleanColumn', 'EmailColumn', 'UrlColumn', 'DateColumn::relative()'], ['templates/columns/stock_level.html.twig']),
            new Page('filters', 'essentials', FilteredOrdersDataTable::class, 'guide/filters/', ['TextFilter', 'ChoiceFilter', 'DateRangeFilter', 'TernaryFilter', 'CheckboxFilter']),
            new Page('attributes', 'essentials', CustomersDataTable::class, 'reference/attributes/', ['#[AsDataTable]', '#[DataTableColumn]', '#[DataTableFilter]'], ['src/Entity/Customer.php']),
            new Page('actions', 'interactions', ProductActionsDataTable::class, 'columns/action-column/', ['Action::detail()', 'collapsible()', 'Action::edit()', 'Action::delete()', 'displayIf()', 'renderAsSwitch()'], ['src/Security/ProductVoter.php', 'templates/rows/product_detail.html.twig']),
            new Page('bulk_actions', 'interactions', BulkOrdersDataTable::class, 'features/bulk-actions/', ['BulkAction', 'askConfirmation()', 'setPermission()', 'BulkActionContext::skip()', 'successMessage()'], ['src/Security/OrderVoter.php']),
            new Page('export', 'interactions', ExportProductsDataTable::class, 'extensions/buttons/', ['Button::copy()', 'Button::csv()', 'Button::excel()', 'Button::pdf()', 'Button::print()', 'setExportable()']),
            new Page('layout', 'extensions', LayoutOrdersDataTable::class, 'extensions/scroller/', ['scroller()', 'scrollY()', 'scrollX()', 'fixedColumns()']),
            new Page('column_tools', 'extensions', ColumnToolsDataTable::class, 'extensions/column-control/', ['columnControl()', 'colReorder()', 'rowGroup()', 'keyTable()', 'Button::colVis()']),
        ];
    }

    public static function find(string $route): ?Page
    {
        foreach (self::all() as $page) {
            if ($page->route === $route) {
                return $page;
            }
        }

        return null;
    }
}
