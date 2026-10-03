<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Product;
use App\Enum\Category;
use App\Enum\ProductStatus;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\ImageColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\Button;

#[AsDataTable(Product::class)]
final class ShowcaseProductsDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield ImageColumn::new('image', 'Image')->setImageWidth(40)->setImageHeight(40)->rounded()->setOrderable(false);
        yield TextColumn::new('name', 'Product');
        yield ChoiceColumn::new('category', 'Category')->setChoices(Category::class)->renderAsBadges();
        yield MoneyColumn::new('price', 'Price')->currency('EUR')->storedAsCents()->hideWhenUpdating();
        yield NumberColumn::new('stock', 'Stock');
        yield ChoiceColumn::new('status', 'Status')
            ->setChoices(ProductStatus::class)
            ->renderAsBadges(['active' => 'success', 'draft' => 'warning', 'archived' => 'secondary']);
        yield BooleanColumn::new('featured', 'Featured')->renderAsSwitch();
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->pageLength(5)
            ->lengthMenu([5, 10, 25])
            ->order([['name' => 'name', 'dir' => 'asc']])
            ->responsive()
            ->layout(['topEnd' => Feature::SEARCH])
            ->buttons([Button::copy(), Button::csv(), Button::excel()], 'topEnd');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Action::edit('Edit')->icon(Icon::Pencil))
            ->add(Action::delete('Delete')->icon(Icon::Trash2)->askConfirmation('Delete this product?'));
    }
}
