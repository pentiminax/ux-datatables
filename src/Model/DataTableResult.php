<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

final readonly class DataTableResult
{
    public function __construct(
        public int $recordsTotal,
        public int $recordsFiltered,
        public iterable $data,
        /**
         * Footer figures over every matching row, by column name then summary type, or null when
         * no column declares a summary.
         *
         * @var array<string, array<string, int|float|string|null>>|null
         */
        public ?array $summaries = null,
    ) {
    }
}
