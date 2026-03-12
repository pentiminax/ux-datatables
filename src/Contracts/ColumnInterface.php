<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Dto\ColumnDto;

interface ColumnInterface extends \JsonSerializable
{
    public function getAsDto(): ColumnDto;

    public function getName(): string;

    public function getField(): ?string;

    public function setField(string $field): static;

    public function setVisible(bool $visible): static;

    public function isSearchable(): bool;

    public function isGlobalSearchable(): bool;

    public function getData(): ?string;
}
