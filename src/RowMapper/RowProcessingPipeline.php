<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;

final class RowProcessingPipeline implements RowMapperInterface
{
    /**
     * @param ColumnInterface[]     $columns
     * @param \Closure(mixed):array $baseMapper
     */
    public function __construct(
        private readonly \Closure $baseMapper,
        private readonly array $columns,
        private readonly ?TemplateColumnRenderer $templateColumnRenderer = null,
        private readonly ?ActionRowDataResolver $actionRowDataResolver = null,
    ) {
    }

    public function map(mixed $row): array
    {
        $mappedRow = ($this->baseMapper)($row);

        if (null !== $this->templateColumnRenderer) {
            $mappedRow = $this->templateColumnRenderer->renderRow(
                row: $mappedRow,
                mappedRow: $row,
                columns: $this->columns,
            );
        }

        if (null !== $this->actionRowDataResolver) {
            $mappedRow = $this->actionRowDataResolver->resolveRow($mappedRow, $row, $this->columns);
        }

        return $mappedRow;
    }
}
