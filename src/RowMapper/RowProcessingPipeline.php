<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;

final class RowProcessingPipeline implements RowMapperInterface
{
    /** @var RowStageInterface[] */
    private array $stages = [];

    /**
     * @param ColumnInterface[]     $columns
     * @param \Closure(mixed):array $baseMapper
     */
    public function __construct(
        private readonly \Closure $baseMapper,
        private readonly array $columns,
        private readonly ColumnResolver $columnResolver = new ColumnResolver(),
    ) {
    }

    public function add(RowStageInterface $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    public function map(mixed $row): array
    {
        $visibleColumns = $this->columnResolver->filterStaticPermissions($this->columns);
        $mappedRow      = $this->columnResolver->removeDeniedColumnValues(
            ($this->baseMapper)($row),
            $this->columns,
        );

        foreach ($this->stages as $stage) {
            $mappedRow = $stage->process($mappedRow, $row, $visibleColumns);
        }

        return $mappedRow;
    }
}
