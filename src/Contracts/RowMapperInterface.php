<?php

namespace Pentiminax\UX\DataTables\Contracts;

interface RowMapperInterface
{
    public function map(object|array $item): array;
}
