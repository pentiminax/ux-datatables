<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;

class TestDataTableWithoutAttribute extends AbstractDataTable
{
    public function __construct(
        private readonly ?ApiResourceCollectionUrlResolver $apiResourceCollectionUrlResolver = null,
        private readonly ?MercureConfigResolver $mercureConfigResolver = null,
    ) {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(new AuthorizationChecker(), $this->apiResourceCollectionUrlResolver, $this->mercureConfigResolver)
        ));
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
