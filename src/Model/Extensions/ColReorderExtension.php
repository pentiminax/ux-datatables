<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ColReorderExtension extends AbstractExtension
{
    /**
     * @param string|list<int> $columns    column selector restricting which columns end users can
     *                                     reorder — a DataTables column-selector string (e.g.
     *                                     ':not(:first-child)') or a list of column indexes
     * @param list<int>|null   $headerRows restricts reordering to these header row indexes, or null
     *                                     for every row
     * @param list<int>|null   $order      initial column order (original column indexes in their
     *                                     new positions), or null to keep document order
     */
    public function __construct(
        private readonly bool $enable = true,
        private readonly string|array $columns = '',
        private readonly ?array $headerRows = null,
        private readonly ?array $order = null,
    ) {
    }

    public function getKey(): string
    {
        return 'colReorder';
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'enable'     => $this->enable,
            'columns'    => $this->columns,
            'headerRows' => $this->headerRows,
            'order'      => $this->order,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
