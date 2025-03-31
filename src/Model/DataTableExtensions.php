<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Model\Extensions\ExtensionInterface;

class DataTableExtensions
{
    private array $extensions;

    public function __construct(array $extensions = [])
    {
        $this->extensions = $extensions;

    }

    public function addExtension(ExtensionInterface $extension): void
    {
        $this->extensions[$extension->getKey()] = $extension->toArray();

    }

    public function toArray(): array
    {
        return $this->extensions;
    }
}