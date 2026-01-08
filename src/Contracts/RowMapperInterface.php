<?php

namespace Pentiminax\UX\DataTables\Contracts;

interface RowMapperInterface
{
    public function map(mixed $row): array;
}
