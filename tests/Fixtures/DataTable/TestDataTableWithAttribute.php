<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;

#[AsDataTable(entityClass: \stdClass::class)]
class TestDataTableWithAttribute extends AbstractDataTable
{
    public function __construct(
        ?ApiResourceCollectionUrlResolverInterface $apiResourceCollectionUrlResolver = null,
        ?MercureConfigResolverInterface $mercureConfigResolver = null,
    ) {
        parent::__construct(
            renderingPreparer: new RenderingPreparer($apiResourceCollectionUrlResolver, $mercureConfigResolver),
        );
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
