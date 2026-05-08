<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;

final class DataTableInfrastructure
{
    public function __construct(
        private readonly ColumnResolver $columnResolver,
        private readonly RenderingPreparer $renderingPreparer,
        private readonly DataTableRuntimeFactory $runtimeFactory,
    ) {
    }

    public static function createDefault(
        ?ColumnResolver $columnResolver = null,
        ?RenderingPreparer $renderingPreparer = null,
        ?DataTableRuntimeFactory $runtimeFactory = null,
    ): self {
        return new self(
            columnResolver: $columnResolver       ?? new ColumnResolver(),
            renderingPreparer: $renderingPreparer ?? new RenderingPreparer(),
            runtimeFactory: $runtimeFactory       ?? new DataTableRuntimeFactory(),
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
}
