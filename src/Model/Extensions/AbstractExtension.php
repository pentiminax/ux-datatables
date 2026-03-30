<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Contracts\ExtensionInterface;

abstract class AbstractExtension implements ExtensionInterface
{
    protected bool $enabled = false;

    abstract public function getKey(): string;

    public function enabled(bool $enabled = true): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
