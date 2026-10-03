<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Security\OrderVoter;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Mutation\BulkRecords;

#[AsDataTable(Order::class)]
final class BulkOrdersDataTable extends AbstractDataTable
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
        yield DateColumn::new('placedAt', 'Placed at')->setFormat('Y-m-d H:i');
        yield DateColumn::new('shippedAt', 'Shipped at')->setFormat('Y-m-d H:i')->setDefaultContent('—');
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide()->processing()->order([['name' => 'placedAt', 'dir' => 'desc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('status')->label('Status')->options(OrderStatus::class)->multiple());
    }

    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return $actions
            ->add(
                BulkAction::new('ship', 'Mark as shipped')
                    ->icon(Icon::Truck)
                    ->setPermission(OrderVoter::SHIP, static fn (Order $order): Order => $order)
                    ->successMessage('Orders marked as shipped.')
                    ->handler(static function (BulkRecords $records): void {
                        foreach ($records as $order) {
                            $order->status    = OrderStatus::Shipped;
                            $order->shippedAt = new \DateTimeImmutable();
                        }
                    })
            )
            ->add(
                BulkAction::new('cancel', 'Cancel')
                    ->icon(Icon::Ban)
                    ->askConfirmation('Cancel {count} orders?')
                    ->setPermission(OrderVoter::CANCEL, static fn (Order $order): Order => $order)
                    ->successMessage('Orders cancelled.')
                    ->handler(static function (BulkRecords $records): void {
                        foreach ($records as $order) {
                            $order->status = OrderStatus::Cancelled;
                        }
                    })
            );
    }
}
