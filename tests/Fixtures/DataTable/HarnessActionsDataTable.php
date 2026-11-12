<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;

final class HarnessActionsDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([['id' => 1, 'title' => 'Symfony 7', 'active' => true]]);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TextColumn::new('title');
        yield BooleanColumn::new('active')->renderAsSwitch();
        yield ActionColumn::fromActions(
            'actions',
            'Actions',
            (new Actions())->add(Action::detail())->add(Action::edit())->add(Action::delete()),
        );
    }
}
