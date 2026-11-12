<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Column\VisibleColumns;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;

final class RowProcessingPipeline implements RowMapperInterface
{
    /** @var RowStageInterface[] */
    private array $stages = [];

    private ?VisibleColumns $visibleColumns = null;

    /**
     * @param ColumnInterface[]                $columns
     * @param \Closure(mixed):array            $baseMapper
     * @param (\Closure():VisibleColumns)|null $visibleColumnsFactory
     */
    public function __construct(
        private readonly \Closure $baseMapper,
        private readonly array $columns,
        private readonly ColumnResolver $columnResolver = new ColumnResolver(),
        private readonly UrlColumnDataResolver $urlColumnDataResolver = new UrlColumnDataResolver(),
        private readonly TemplateColumnRenderer $templateColumnRenderer = new TemplateColumnRenderer(),
        private readonly ActionRowDataResolver $actionRowDataResolver = new ActionRowDataResolver(),
        private readonly ?string $dataTableClass = null,
        private ?\Closure $visibleColumnsFactory = null,
    ) {
    }

    public function add(RowStageInterface $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    public function map(mixed $row): array
    {
        // Static permissions do not depend on the row. Safe to memoize because a pipeline is built per request.
        $visible = $this->visibleColumns ??= $this->visibleColumnsFactory
            ? ($this->visibleColumnsFactory)()
            : $this->columnResolver->visibleColumns($this->columns, $this->dataTableClass);
        $visibleColumns = $visible->columns;
        $mappedRow      = $this->columnResolver->removePaths(($this->baseMapper)($row), $visible->deniedPaths);

        foreach ($this->stages as $stage) {
            $mappedRow = $stage->process($mappedRow, $row, $visibleColumns);
        }

        $mappedRow = $this->urlColumnDataResolver->resolveRow($mappedRow, $row, $visibleColumns);
        $mappedRow = $this->templateColumnRenderer->renderRow(
            row: $mappedRow,
            mappedRow: $row,
            columns: $visibleColumns,
        );

        return $this->actionRowDataResolver->resolveRow($mappedRow, $row, $visibleColumns, $this->dataTableClass);
    }
}
