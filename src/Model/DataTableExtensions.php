<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ExtensionInterface;
use Pentiminax\UX\DataTables\Model\Extensions\KeyTableExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ResponsiveExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ScrollerExtension;
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
     * @param ButtonType[]|string[] $buttons
     */
    public function addButtonsExtension(array $buttons): static
    {
        $this->addExtension(new ButtonsExtension($buttons));

        return $this;
    }

    public function addColumnControlExtension(): static
    {
        $this->addExtension(new ColumnControlExtension());

        return $this;
    }

    public function addResponsiveExtension(): static
    {
        $this->addExtension(new ResponsiveExtension());

        return $this;
    }

    public function addSelectExtension(): static
    {
        $this->addExtension(new SelectExtension());

        return $this;
    }

    public function addKeyTableExtension(): static
    {
        $this->addExtension(new KeyTableExtension());

        return $this;
    }

    public function addScrollerExtension(): static
    {
        $this->addExtension(new ScrollerExtension());

        return $this;
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
