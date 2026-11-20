<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

final class HarnessHydratedDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TextColumn::new('title');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider(
            [new HarnessBook(1, 'Symfony 7'), new HarnessBook(2, 'UX in Action')],
            $this->createRowMapper(),
            $this->getResolvedColumns(),
        );
    }
}
