<?php

namespace Pentiminax\UX\DataTables\Contracts;

interface ColumnInterface extends \JsonSerializable
{
    public static function new(string $name, string $title = ''): self;
}
