<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntentFactoryInterface;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;

final class DataTableInfrastructure
{
    public function __construct(
        private readonly ColumnResolver $columnResolver,
        private readonly RenderingPreparer $renderingPreparer,
        private readonly DataTableRuntimeFactory $runtimeFactory,
        private readonly DataTableQueryIntentFactoryInterface $queryIntentFactory,
        private readonly QueryFilterPipeline $queryFilterPipeline,
    ) {
    }

    public static function createDefault(
        ?ColumnResolver $columnResolver = null,
        ?RenderingPreparer $renderingPreparer = null,
        ?DataTableRuntimeFactory $runtimeFactory = null,
        ?DataTableQueryIntentFactoryInterface $queryIntentFactory = null,
        ?QueryFilterPipeline $queryFilterPipeline = null,
    ): self {
        $queryIntentFactory ??= new DefaultDataTableQueryIntentFactory();

        return new self(
            columnResolver: $columnResolver       ?? new ColumnResolver(),
            renderingPreparer: $renderingPreparer ?? new RenderingPreparer(),
            runtimeFactory: $runtimeFactory       ?? new DataTableRuntimeFactory(),
            queryIntentFactory: $queryIntentFactory,
            queryFilterPipeline: $queryFilterPipeline ?? new QueryFilterPipeline($queryIntentFactory),
        );
    }

    public function columnResolver(): ColumnResolver
    {
        return $this->columnResolver;
    }

    public function renderingPreparer(): RenderingPreparer
    {
        return $this->renderingPreparer;
    }

    public function runtimeFactory(): DataTableRuntimeFactory
    {
        return $this->runtimeFactory;
    }

    public function queryIntentFactory(): DataTableQueryIntentFactoryInterface
    {
        return $this->queryIntentFactory;
    }

    public function queryFilterPipeline(): QueryFilterPipeline
    {
        return $this->queryFilterPipeline;
    }
}
