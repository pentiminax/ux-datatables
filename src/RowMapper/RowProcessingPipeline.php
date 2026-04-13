<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

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
    ) {
    }

    public function add(RowStageInterface $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    public function map(mixed $row): array
    {
        $mappedRow = ($this->baseMapper)($row);

        foreach ($this->stages as $stage) {
            $mappedRow = $stage->process($mappedRow, $row, $this->columns);
        }

        return $mappedRow;
    }
}
