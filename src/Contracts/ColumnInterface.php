<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Enum\ColumnType;

interface ColumnInterface extends \JsonSerializable
{
    public function getName(): string;

    public function getField(): ?string;

    public function setField(string $field): static;

    public function setVisible(bool $visible): static;

    public function isSearchable(): bool;

    public function isGlobalSearchable(): bool;

    public function getData(): ?string;

    public function getTitle(): ?string;

    public function isNumber(): bool;

    public function isDate(): bool;

    public function getType(): ColumnType;

    public function isVisible(): bool;

    public function isOrderable(): bool;

    public function isExportable(): bool;

    public function getWidth(): ?string;

    public function getClassName(): ?string;

    public function getCellType(): ?string;

    public function getRender(): ?string;

    public function getDefaultContent(): ?string;

    public function getCustomOption(string $optionName): mixed;

    public function getCustomOptions(): array;
}
