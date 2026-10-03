<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Product;
use App\Enum\Category;
use App\Enum\ProductStatus;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\Button;

#[AsDataTable(Product::class)]
final class ColumnToolsDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield ChoiceColumn::new('category', 'Category')->setChoices(Category::class);
        yield TextColumn::new('name', 'Product');
        yield TextColumn::new('sku', 'SKU');
        yield MoneyColumn::new('price', 'Price')->currency('EUR')->storedAsCents();
        yield NumberColumn::new('stock', 'Stock');
        yield ChoiceColumn::new('status', 'Status')->setChoices(ProductStatus::class);
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->pageLength(25)
            ->order([['name' => 'category', 'dir' => 'asc']])
            ->rowGroup('category')
            ->colReorder()
            ->columnControl()
            ->keyTable()
            ->buttons([Button::colVis()]);
    }
}
