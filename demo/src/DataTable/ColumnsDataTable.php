<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Product;
use App\Enum\Category;
use App\Enum\ProductStatus;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Column\ImageColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

#[AsDataTable(Product::class)]
final class ColumnsDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield NumberColumn::new('id', 'ID');
        yield ImageColumn::new('image', 'Image')->setImageWidth(36)->setImageHeight(36)->rounded();
        yield TextColumn::new('name', 'Product');
        yield ChoiceColumn::new('category', 'Category')->setChoices(Category::class);
        yield MoneyColumn::new('price', 'Price')->currency('EUR')->storedAsCents();
        yield TextColumn::new('priceBand', 'Price band')
            ->setField('price')
            ->formatValueUsing(static fn (int $cents): string => match (true) {
                $cents < 2000  => '€',
                $cents < 10000 => '€€',
                default        => '€€€',
            });
        yield IconColumn::new('stockIcon', 'In stock')
            ->setField('stock')
            ->icon(static fn (int $stock): Icon => $stock > 0 ? Icon::PackageCheck : Icon::PackageX)
            ->color(static fn (int $stock): string => $stock > 0 ? 'success' : 'danger');
        yield TemplateColumn::new('stockLevel', 'Stock level')
            ->setField('stock')
            ->setTemplate('columns/stock_level.html.twig');
        yield ChoiceColumn::new('status', 'Status')
            ->setChoices(ProductStatus::class)
            ->renderAsBadges(['active' => 'success', 'draft' => 'warning', 'archived' => 'secondary']);
        yield BooleanColumn::new('featured', 'Featured')->renderAsSwitch();
        yield EmailColumn::new('supplierEmail', 'Supplier');
        yield UrlColumn::new('url', 'Shop page')->setDisplayValue('Open')->openInNewTab()->showExternalIcon();
        yield DateColumn::new('createdAt', 'Added')->relative();
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->pageLength(10)->scrollX(true);
    }
}
