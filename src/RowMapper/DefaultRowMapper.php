<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

use Pentiminax\UX\DataTables\Column\Rendering\ColumnKeyResolver;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;

final class DefaultRowMapper implements RowMapperInterface
{
    /**
     * @param ColumnInterface[] $columns
     */
    public function __construct(
        private readonly array $columns,
    ) {
    }

    public function map(mixed $row): array
    {
        if ($row instanceof RowContext) {
            $row = $row->item;
        }

        if (\is_array($row)) {
            return $row;
        }

        if ($row instanceof \JsonSerializable) {
            return $row->jsonSerialize();
        }

        if (!\is_object($row)) {
            return (array) $row;
        }

        return $this->mapObjectRow($row);
    }

    private function mapObjectRow(object $row): array
    {
        $mapped = [];

        foreach ($this->columns as $column) {
            if ($column instanceof ActionsProvidingColumnInterface) {
                continue;
            }

            $key = ColumnKeyResolver::rowKey($column);
            if (null === $key) {
                continue;
            }

            $mapped[$key] = PropertyReader::readPath($row, ColumnKeyResolver::readPath($column, $key));
        }

        return $mapped ?: get_object_vars($row);
    }
}
