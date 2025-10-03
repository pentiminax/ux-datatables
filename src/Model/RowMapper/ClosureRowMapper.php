<?php

namespace Pentiminax\UX\DataTables\Model\RowMapper;

use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;

final class ClosureRowMapper implements RowMapperInterface
{
    public function __construct(
        private readonly \Closure $closure
    ) {}

    public function map(mixed $item): array
    {
        return ($this->closure)($item);
    }
}