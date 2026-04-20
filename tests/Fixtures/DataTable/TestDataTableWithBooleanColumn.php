<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(entityClass: ToggleEntityFixture::class)]
class TestDataTableWithBooleanColumn extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('isEmailAuthEnabled')->renderAsSwitch();
    }
}
