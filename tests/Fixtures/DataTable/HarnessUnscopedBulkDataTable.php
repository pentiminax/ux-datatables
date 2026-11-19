<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * A server-side table with a bulk action on a provider that cannot check the row scope.
 */
final class HarnessUnscopedBulkDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return $actions->add(BulkAction::new('touch')->handler(static fn () => null));
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([], $this->createRowMapper(), $this->getResolvedColumns());
    }
}
