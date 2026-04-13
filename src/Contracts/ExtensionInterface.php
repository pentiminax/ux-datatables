<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

interface ExtensionInterface extends \JsonSerializable
{
    public function getKey(): string;

    public function enabled(bool $enabled = true): static;

    public function isEnabled(): bool;
}
