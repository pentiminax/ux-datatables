<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

interface RowMapperInterface
{
    public function map(mixed $row): array;
}
