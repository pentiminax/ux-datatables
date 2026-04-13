<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;

final class TemplateRenderingStage implements RowStageInterface
{
    public function __construct(
        private readonly TemplateColumnRenderer $renderer,
    ) {
    }

    public function process(array $mappedRow, mixed $originalRow, array $columns): array
    {
        return $this->renderer->renderRow(
            row: $mappedRow,
            mappedRow: $originalRow,
            columns: $columns,
        );
    }
}
