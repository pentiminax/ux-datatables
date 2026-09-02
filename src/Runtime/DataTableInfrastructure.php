<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;

final class DataTableInfrastructure
{
    public function __construct(
        public readonly ColumnResolver $columnResolver,
        public readonly RenderingPreparer $renderingPreparer,
        public readonly DataTableRuntimeFactory $runtimeFactory,
        public readonly DefaultDataTableQueryIntentFactory $queryIntentFactory,
        public readonly QueryFilterPipeline $queryFilterPipeline,
        public readonly array $options = [],
        public readonly array $attributes = [],
        public readonly array $extensions = [],
        public readonly ?DataTableProfiler $profiler = null,
    ) {
    }

    public static function createDefault(
        ?ColumnResolver $columnResolver = null,
        ?RenderingPreparer $renderingPreparer = null,
        ?DataTableRuntimeFactory $runtimeFactory = null,
        ?DefaultDataTableQueryIntentFactory $queryIntentFactory = null,
        ?QueryFilterPipeline $queryFilterPipeline = null,
        array $options = [],
        array $attributes = [],
        array $extensions = [],
        ?DataTableProfiler $profiler = null,
    ): self {
        $queryIntentFactory ??= new DefaultDataTableQueryIntentFactory();

        return new self(
            columnResolver: $columnResolver       ?? new ColumnResolver(),
            renderingPreparer: $renderingPreparer ?? new RenderingPreparer(),
            runtimeFactory: $runtimeFactory       ?? new DataTableRuntimeFactory(),
            queryIntentFactory: $queryIntentFactory,
            queryFilterPipeline: $queryFilterPipeline ?? new QueryFilterPipeline($queryIntentFactory),
            options: $options,
            attributes: $attributes,
            extensions: $extensions,
            profiler: $profiler,
        );
    }

    /**
     * Stamps the bundle-wide `data_tables` defaults onto every table it creates.
     */
    public function createDataTable(string $id): DataTable
    {
        return new DataTable($id, $this->options, $this->attributes, $this->extensions);
    }
}
