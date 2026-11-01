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
        $referenceColumn = TextColumn::new('reference', 'Reference');

        $customerColumn = TextColumn::new('customer', 'Customer')
            ->setField('customer.name');

        $totalColumn = MoneyColumn::new('total', 'Total')
            ->currency('EUR')
            ->storedAsCents();

        $statusColumn = ChoiceColumn::new('status', 'Status')
            ->setChoices(OrderStatus::class)
            ->renderAsBadges([
                'pending'   => 'warning',
                'paid'      => 'info',
                'shipped'   => 'primary',
                'delivered' => 'success',
                'cancelled' => 'danger',
            ]);

        $expressColumn = IconColumn::new('express', 'Express')
            ->boolean()
            ->trueIcon(Icon::Zap)
            ->falseIcon(Icon::Minus)
            ->trueColor('warning')
            ->falseColor('secondary');

        $placedAtColumn = DateColumn::new('placedAt', 'Placed at')
            ->setFormat('Y-m-d');

        yield $referenceColumn;
        yield $customerColumn;
        yield $totalColumn;
        yield $statusColumn;
        yield $expressColumn;
        yield $placedAtColumn;
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->processing()
            ->order([['name' => 'placedAt', 'dir' => 'desc']])
            // Reference, customer, status and placed at: the total is stored in cents, so a typed amount would mislead.
            ->searchBuilder(columns: [0, 1, 3, 5]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $customerFilter = TextFilter::new('customer')
            ->field('customer.name')
            ->label('Customer')
            ->placeholder('Name contains…');

        $statusFilter = ChoiceFilter::new('status')
            ->label('Status')
            ->options(OrderStatus::class)
            ->multiple();

        $placedAtFilter = DateRangeFilter::new('placedAt')
            ->label('Placed between');

        $expressFilter = TernaryFilter::new('express')
            ->label('Express shipping')
            ->values(true, false);

        $largeOrdersFilter = CheckboxFilter::new('large')
            ->label('Orders over €500')
            ->query(static fn (QueryBuilder $qb, mixed $value, string $alias) => $qb->andWhere("$alias.total >= 50000"));

        return $filters
            ->add($customerFilter)
            ->add($statusFilter)
            ->add($placedAtFilter)
            ->add($expressFilter)
            ->add($largeOrdersFilter);
    }
}
