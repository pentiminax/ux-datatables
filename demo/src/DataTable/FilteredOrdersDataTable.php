<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\CheckboxFilter;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\DateRangeFilter;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Order::class)]
final class FilteredOrdersDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('reference', 'Reference');
        yield TextColumn::new('customer', 'Customer')->setField('customer.name');
        yield MoneyColumn::new('total', 'Total')->currency('EUR')->storedAsCents();
        yield ChoiceColumn::new('status', 'Status')
            ->setChoices(OrderStatus::class)
            ->renderAsBadges([
                'pending'   => 'warning',
                'paid'      => 'info',
                'shipped'   => 'primary',
                'delivered' => 'success',
                'cancelled' => 'danger',
            ]);
        yield IconColumn::new('express', 'Express')
            ->boolean()
            ->trueIcon(Icon::Zap)
            ->falseIcon(Icon::Minus)
            ->trueColor('warning')
            ->falseColor('secondary');
        yield DateColumn::new('placedAt', 'Placed at')->setFormat('Y-m-d');
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide()->processing()->order([['name' => 'placedAt', 'dir' => 'desc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('customer')->field('customer.name')->label('Customer')->placeholder('Name contains…'))
            ->add(ChoiceFilter::new('status')->label('Status')->options(OrderStatus::class)->multiple())
            ->add(DateRangeFilter::new('placedAt')->label('Placed between'))
            ->add(TernaryFilter::new('express')->label('Express shipping')->values(true, false))
            ->add(
                CheckboxFilter::new('large')
                    ->label('Orders over €500')
                    ->query(static fn (QueryBuilder $qb, mixed $value, string $alias) => $qb->andWhere("$alias.total >= 50000"))
            );
    }
}
