<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

final readonly class Search
{
    public function __construct(
        public ?string $value,
        public bool $regex,
    ) {
    }
}
