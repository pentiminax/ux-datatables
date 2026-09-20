<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;

#[AsDataTable(
    entityClass: \stdClass::class,
    editModalTemplate: 'datatables/attribute_edit_modal.html.twig',
    editModalAdapter: 'bs5',
)]
class TestDataTableWithEditModalAttribute extends AbstractDataTable
{
    public function __construct()
    {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(),
        ));
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([], $this->createRowMapper());
    }
}
