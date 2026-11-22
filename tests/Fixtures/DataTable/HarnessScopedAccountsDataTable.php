<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Mutation\BulkRecords;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountAccount;

/**
 * Every row action and a bulk action over accounts whose permanent scope hides the one named "hidden".
 *
 * @internal
 */
#[AsDataTable(entityClass: CountAccount::class)]
final class HarnessScopedAccountsDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('name');
        yield BooleanColumn::new('active')->renderAsSwitch();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Action::delete())
            ->add(Action::edit())
            ->add(Action::detail()->collapsible('harness/detail.html.twig'));
    }

    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return $actions->add(BulkAction::new('deactivate')->handler(static function (BulkRecords $records): void {
            foreach ($records as $account) {
                $account->active = false;
            }
        }));
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        return $qb->andWhere("e.name <> 'hidden'");
    }
}
