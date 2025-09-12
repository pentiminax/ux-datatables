<?php

namespace Pentiminax\UX\DataTables\Model;

interface ColumnInterface extends \JsonSerializable
{
    public function getName(): string;
}