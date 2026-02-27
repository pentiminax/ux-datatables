<?php

namespace Pentiminax\UX\DataTables\RowMapper;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Util\PropertyReader;

final class DefaultRowMapper implements RowMapperInterface
{
    /**
     * @param AbstractColumn[] $columns
     */
    public function __construct(
        private readonly array $columns,
    ) {
    }

    public function map(mixed $row): array
    {
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
            $key = $this->resolveColumnKey($column);
            if (null === $key) {
                continue;
            }

            $value = PropertyReader::readPath($row, $key);
            if ($column instanceof DateColumn && $value instanceof \DateTimeInterface) {
                if ($column->getFormat()) {
                    $value = $value->format($column->getFormat());
                } else {
                    $value = $value->format('Y-m-d');
                }
            }

            $mapped[$key] = $value;
        }

        return $mapped ?: get_object_vars($row);
    }

    private function resolveColumnKey(ColumnInterface $column): ?string
    {
        $key = $column->getData() ?? $column->getName();

        return null === $key || '' === $key ? null : $key;
    }
}
