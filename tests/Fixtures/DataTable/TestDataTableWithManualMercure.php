<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;

#[AsDataTable(entityClass: \stdClass::class, mercure: true)]
class TestDataTableWithManualMercure extends AbstractDataTable
{
    public function __construct(
        ?ApiResourceCollectionUrlResolverInterface $apiResourceCollectionUrlResolver = null,
        ?MercureConfigResolverInterface $mercureConfigResolver = null,
        ?MercureHubUrlResolverInterface $mercureHubUrlResolver = null,
    ) {
        parent::__construct(
            renderingPreparer: new RenderingPreparer(
                $apiResourceCollectionUrlResolver,
                $mercureConfigResolver,
                null,
                $mercureHubUrlResolver,
            ),
        );
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->ajax('/api/books')
            ->mercure(topics: ['manual/topic']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
