<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;

/**
 * A ColumnControl criterion for a single column.
 *
 * Either a list criterion (logic {@see ColumnControlLogic::In} with non-empty $values)
 * or a scalar search criterion (any other logic with a non-empty $value). List criteria
 * win over scalar search when both are present, matching the current filter branch order.
 */
final readonly class ColumnControlIntent
{
    /**
     * @param list<string> $values
     */
    public function __construct(
        public ColumnReadReference $column,
        public ColumnControlLogic $logic,
        public string $valueType,
        public ?string $value = null,
        public array $values = [],
    ) {
    }

    public function isList(): bool
    {
        return ColumnControlLogic::In === $this->logic;
    }
}
