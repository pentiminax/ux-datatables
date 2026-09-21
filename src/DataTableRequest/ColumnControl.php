<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataTableRequest;

final readonly class ColumnControl
{
    public function __construct(
        public ?ColumnControlSearch $search = null,
        public array $list = [],
    ) {
    }

    /**
     * Transport-level input: a client controls both the shape and the types here, so
     * anything that is not a well-formed search or a list of scalars is dropped rather
     * than aborting the whole Ajax request.
     */
    public static function fromArray(array $data): self
    {
        $search = $data['search'] ?? null;
        $list   = $data['list']   ?? [];

        return new self(
            search: \is_array($search) ? ColumnControlSearch::fromArray($search) : null,
            list: \is_array($list) ? array_values(array_filter($list, \is_scalar(...))) : [],
        );
    }
}
