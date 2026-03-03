<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

class AutoDetectNoAttributeDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    /**
     * @return AbstractColumn[]
     */
    public function callAutoDetectColumns(): array
    {
        return $this->autoDetectColumns();
    }
}
