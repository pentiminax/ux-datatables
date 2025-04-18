<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

interface ExtensionInterface extends \JsonSerializable
{
    public  function getKey(): string;
}