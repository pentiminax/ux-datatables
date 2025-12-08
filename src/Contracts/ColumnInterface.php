<?php

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Dto\ColumnDto;

interface ColumnInterface extends \JsonSerializable
{
    public static function new(string $name, string $title = ''): self;

    public function getAsDto(): ColumnDto;

    public function getName(): string;
}
