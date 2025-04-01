<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ExtensionInterface;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;

class DataTableExtensions
{
    /** @var ExtensionInterface[] */
    private array $extensions = [];

    public function __construct(array $extensions = [])
    {
        if (isset($extensions['buttons'])) {
            $this->extensions['buttons'] = new ButtonsExtension($extensions['buttons']);
        }

        if (isset($extensions['select'])) {
            $style = isset($extensions['select']['style']) ? SelectStyle::from($extensions['select']['style']) : SelectStyle::SINGLE;
            $this->extensions['select'] = new SelectExtension($style);
        }
    }

    public function addExtension(ExtensionInterface $extension): void
    {
        $this->extensions[$extension->getKey()] = $extension;

    }

    public function toArray(): array
    {
        $extensions = [];
        foreach ($this->extensions as $extension) {
            $extensions[$extension->getKey()] = $extension->toArray();
        }

        return $extensions;
    }
}