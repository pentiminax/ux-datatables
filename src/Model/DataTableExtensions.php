<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Contracts\ExtensionInterface;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
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
            $style                      = isset($extensions['select']['style']) ? SelectStyle::from($extensions['select']['style']) : SelectStyle::SINGLE;
            $this->extensions['select'] = new SelectExtension($style);
        }
    }

    public function addExtension(ExtensionInterface $extension): static
    {
        $this->extensions[$extension->getKey()] = $extension;

        return $this;
    }

    /**
     * Every registered extension, including layout-aware ones that
     * {@see self::jsonSerialize()} deliberately omits from the client payload.
     *
     * @return array<string, ExtensionInterface>
     */
    public function all(): array
    {
        return $this->extensions;
    }

    public function getButtonsExtension(): ?ButtonsExtension
    {
        return $this->extensions['buttons'] ?? null;
    }

    /**
     * Buttons is injected into the DataTables `layout` configuration rather than serialized as a
     * top-level option, so it is skipped here and consumed by layout-building code instead.
     */
    public function jsonSerialize(): array
    {
        $extensions = [];
        foreach ($this->extensions as $extension) {
            if ($extension instanceof ButtonsExtension) {
                continue;
            }

            $extensions[$extension->getKey()] = $extension->jsonSerialize();
        }

        return $extensions;
    }
}
