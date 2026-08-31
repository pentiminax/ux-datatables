<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Builder\DataTableBuilder;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;

final class DataTableInfrastructure
{
    public function __construct(
        public readonly ColumnResolver $columnResolver,
        public readonly RenderingPreparer $renderingPreparer,
        public readonly DataTableRuntimeFactory $runtimeFactory,
        public readonly DefaultDataTableQueryIntentFactory $queryIntentFactory,
        public readonly QueryFilterPipeline $queryFilterPipeline,
        private ?DataTableBuilderInterface $builder = null,
        public readonly ?DataTableProfiler $profiler = null,
    ) {
    }

    public static function createDefault(
        ?ColumnResolver $columnResolver = null,
        ?RenderingPreparer $renderingPreparer = null,
        ?DataTableRuntimeFactory $runtimeFactory = null,
        ?DefaultDataTableQueryIntentFactory $queryIntentFactory = null,
        ?QueryFilterPipeline $queryFilterPipeline = null,
        ?DataTableBuilderInterface $builder = null,
        ?DataTableProfiler $profiler = null,
    ): self {
        $queryIntentFactory ??= new DefaultDataTableQueryIntentFactory();

        return new self(
            columnResolver: $columnResolver       ?? new ColumnResolver(),
            renderingPreparer: $renderingPreparer ?? new RenderingPreparer(),
            runtimeFactory: $runtimeFactory       ?? new DataTableRuntimeFactory(),
            queryIntentFactory: $queryIntentFactory,
            queryFilterPipeline: $queryFilterPipeline ?? new QueryFilterPipeline($queryIntentFactory),
            builder: $builder,
            profiler: $profiler,
        );
    }

    /**
     * Builder seeded with the bundle-wide defaults, so tables created outside
     * of it still inherit the `data_tables` configuration.
     */
    public function builder(): DataTableBuilderInterface
    {
        return $this->builder ??= new DataTableBuilder();
    }
}
