<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataTableRequest;

use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;

final readonly class ColumnControlSearch
{
    public function __construct(
        public string $value,
        public ColumnControlLogic $logic,
        public string $type,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            value: $data['value'],
            logic: ColumnControlLogic::from($data['logic']),
            type: $data['type']
        );
    }
}
