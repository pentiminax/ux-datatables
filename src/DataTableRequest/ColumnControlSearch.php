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

    /**
     * Returns null when the payload cannot describe a search: an unknown or missing logic,
     * a non-scalar value, or a missing column type, all of which a client can send.
     */
    public static function fromArray(array $data): ?self
    {
        $logic = ColumnControlLogic::tryFrom(\is_string($data['logic'] ?? null) ? $data['logic'] : '');
        $value = $data['value'] ?? null;
        $type  = $data['type']  ?? null;

        if (null === $logic || !\is_scalar($value) || !\is_string($type)) {
            return null;
        }

        return new self(
            value: (string) $value,
            logic: $logic,
            type: $type,
        );
    }
}
