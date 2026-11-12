<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

#[AsDataTable(Order::class)]
final class LayoutOrdersDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('reference', 'Reference');

        yield TextColumn::new('customer', 'Customer')
            ->field('customer.name');

        yield EmailColumn::new('email', 'Email')
            ->field('customer.email');

        yield TextColumn::new('country', 'Country')
            ->field('customer.country');

        yield NumberColumn::new('items', 'Items');

        yield MoneyColumn::new('total', 'Total')
            ->currency('EUR')
            ->storedAsCents();

        yield ChoiceColumn::new('status', 'Status')
            ->choices(OrderStatus::class);

        yield IconColumn::new('express', 'Express')
            ->boolean()
            ->trueIcon(Icon::Zap)
            ->falseIcon(Icon::Minus)
            ->trueColor('warning')
            ->falseColor('secondary');

        yield DateColumn::new('placedAt', 'Placed at')
            ->format('Y-m-d H:i');

        yield DateColumn::new('shippedAt', 'Shipped at')
            ->format('Y-m-d H:i')
            ->defaultContent('—');
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->scrollY('420px')
            ->scrollX(true)
            ->scroller()
            ->fixedColumns(start: 1);
    }
}
