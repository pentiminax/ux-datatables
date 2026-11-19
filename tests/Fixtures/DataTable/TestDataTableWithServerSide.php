<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
class TestDataTableWithServerSide extends AbstractDataTable
{
    public function __construct(
        private readonly ?ApiResourceCollectionUrlResolver $apiResourceCollectionUrlResolver = null,
        private readonly ?MercureConfigResolver $mercureConfigResolver = null,
    ) {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(new AuthorizationChecker(), new ColumnResolver(new AuthorizationChecker()), $this->apiResourceCollectionUrlResolver, $this->mercureConfigResolver)
        ));
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
