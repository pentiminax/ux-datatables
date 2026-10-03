<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Product;
use App\Enum\Category;
use App\Enum\ProductStatus;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;

#[AsDataTable(Product::class)]
final class ProductActionsDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('name', 'Product');
        yield TextColumn::new('sku', 'SKU')->hideWhenUpdating();
        yield ChoiceColumn::new('category', 'Category')->setChoices(Category::class);
        yield MoneyColumn::new('price', 'Price')->currency('EUR')->storedAsCents();
        yield NumberColumn::new('stock', 'Stock');
        yield ChoiceColumn::new('status', 'Status')
            ->setChoices(ProductStatus::class)
            ->renderAsBadges(['active' => 'success', 'draft' => 'warning', 'archived' => 'secondary']);
        yield BooleanColumn::new('featured', 'Featured')->renderAsSwitch();
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide()->pageLength(10);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Action::detail('Details')->icon(Icon::ChevronDown)->collapsible('rows/product_detail.html.twig'))
            ->add(Action::edit('Edit')->icon(Icon::Pencil))
            ->add(
                Action::delete('Delete')
                    ->icon(Icon::Trash2)
                    ->askConfirmation('Delete this product?')
                    ->displayIf('status', 'archived')
            );
    }
}
