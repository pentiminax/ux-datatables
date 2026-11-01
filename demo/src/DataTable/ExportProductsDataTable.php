<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Product;
use App\Enum\Category;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\Button;

#[AsDataTable(Product::class)]
final class ExportProductsDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('sku', 'SKU');

        yield TextColumn::new('name', 'Product');

        yield ChoiceColumn::new('category', 'Category')
            ->setChoices(Category::class);

        yield MoneyColumn::new('price', 'Price')
            ->currency('EUR')
            ->storedAsCents();

        yield NumberColumn::new('stock', 'Stock');

        yield TextColumn::new('supplierEmail', 'Supplier')
            ->setExportable(false);
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        $csvButton = Button::csv(serverSide: true)
            ->filename('products');

        $rawCsvButton = Button::csv(serverSide: true)
            ->rawValues()
            ->exportKey('raw')
            ->text('CSV (raw)')
            ->filename('products-raw');

        $excelButton = Button::excel(serverSide: true)
            ->filename('products');

        return $table
            ->serverSide()
            ->pageLength(10)
            ->buttons([
                Button::copy(),
                $csvButton,
                $rawCsvButton,
                $excelButton,
                Button::pdf(),
                Button::print(),
            ]);
    }
}
