<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

interface ExtensionInterface extends \JsonSerializable
{
    public  function getKey(): string;

    public function enabled(bool $enabled = true): self;

    public function isEnabled(): bool;
}