<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ExtensionInterface;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;

class DataTableExtensions implements \JsonSerializable
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

    public function getButtonsExtension(): ?ButtonsExtension
    {
        return $this->extensions['buttons'] ?? null;
    }

    public function jsonSerialize(): array
    {
        $extensions = [];
        foreach ($this->extensions as $extension) {
            if ('buttons' === $extension->getKey()) {
                continue;
            }

            $extensions[$extension->getKey()] = $extension->jsonSerialize();
        }

        return $extensions;
    }
}