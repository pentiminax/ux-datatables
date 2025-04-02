<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

interface ExtensionInterface
{
    public  function getKey(): string;

    public function toArray(): array;
}