<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
class TestDataTableWithAttribute extends AbstractDataTable
{
    public function __construct(
        private readonly ?ApiResourceCollectionUrlResolverInterface $apiResourceCollectionUrlResolver = null,
        private readonly ?MercureConfigResolverInterface $mercureConfigResolver = null,
    ) {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer($this->apiResourceCollectionUrlResolver, $this->mercureConfigResolver)
        ));
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
