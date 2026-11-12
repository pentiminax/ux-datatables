<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

/**
 * Exercises every payload section with the bare fluent API only, so rendering it must raise no
 * deprecation and its payload is pinned by tests/Fixtures/rendered-payload.json.
 *
 * @internal
 */
final class PayloadFixtureDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->data([['id' => 1, 'title' => 'Symfony 7', 'status' => 'published', 'active' => true, 'releasedAt' => '2024-01-02']])
            ->buttons([ButtonType::COPY, ButtonType::CSV])
            ->select(SelectStyle::MULTI, withCheckbox: true)
            ->responsive()
            ->language(Language::FR)
            ->mercure(['/books']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id')->title('Identifier')->width('80px')->orderable(false)->visible(false);
        yield TextColumn::new('title')->searchable()->responsivePriority(1);
        yield ChoiceColumn::new('status')->choices(['published' => 'Published', 'draft' => 'Draft']);
        yield BooleanColumn::new('active')->renderAsSwitch();
        yield DateColumn::new('releasedAt')->format('Y-m-d')->exportable(false);
        yield ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::detail())->add(Action::edit())->add(Action::delete())->add(Action::new('archive', 'Archive')->linkToUrl('/books/archive')));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title')->label('Title'))
            ->add(ChoiceFilter::new('status')->label('Status')->options(['published' => 'Published', 'draft' => 'Draft']));
    }

    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return $actions->add(BulkAction::new('archive', 'Archive')->className('btn btn-warning'));
    }
}
