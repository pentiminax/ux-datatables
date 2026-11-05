<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

/**
 * @internal
 */
final readonly class RowActionRequest
{
    private function __construct(
        public RowActionKind $kind,
        public string $dataTable,
        public int|string $id,
        public ?string $field = null,
        public int|string|null $rowId = null,
    ) {
    }

    public static function delete(string $dataTable, int|string $id): self
    {
        return new self(kind: RowActionKind::Delete, dataTable: $dataTable, id: $id);
    }

    public static function detail(string $dataTable, int|string $id): self
    {
        return new self(kind: RowActionKind::Detail, dataTable: $dataTable, id: $id);
    }

    public static function editFormView(string $dataTable, int|string $id): self
    {
        return new self(kind: RowActionKind::EditFormView, dataTable: $dataTable, id: $id);
    }

    public static function editFormSubmit(string $dataTable, int|string $id): self
    {
        return new self(kind: RowActionKind::EditFormSubmit, dataTable: $dataTable, id: $id);
    }

    /**
     * @param int|string      $targetId the id of the entity the switch changes
     * @param int|string|null $rowId    the table row the switch sits in
     */
    public static function toggle(string $dataTable, string $field, int|string $targetId, int|string|null $rowId = null): self
    {
        return new self(kind: RowActionKind::Toggle, dataTable: $dataTable, id: $targetId, field: $field, rowId: $rowId);
    }
}
