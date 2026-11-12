<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

#[AsDataTable(Order::class)]
final class OrdersDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('reference', 'Reference');

        yield TextColumn::new('customer', 'Customer')
            ->field('customer.name');

        yield NumberColumn::new('items', 'Items');

        yield MoneyColumn::new('total', 'Total')
            ->currency('EUR')
            ->storedAsCents();

        yield ChoiceColumn::new('status', 'Status')
            ->choices(OrderStatus::class)
            ->renderAsBadges([
                'pending'   => 'warning',
                'paid'      => 'info',
                'shipped'   => 'primary',
                'delivered' => 'success',
                'cancelled' => 'danger',
            ]);

        yield DateColumn::new('placedAt', 'Placed at')
            ->format('Y-m-d H:i');
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->processing()
            ->pageLength(25)
            ->order([['name' => 'placedAt', 'dir' => 'desc']])
            ->urlState()
            ->fixedHeader();
    }
}
