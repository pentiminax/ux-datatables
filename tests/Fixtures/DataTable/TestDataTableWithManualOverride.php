<?php

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(entityClass: \stdClass::class)]
class TestDataTableWithManualOverride extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function getDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([], $this->rowMapper());
    }
}
