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
        return new self(RowActionKind::Delete, $dataTable, $id);
    }

    public static function detail(string $dataTable, int|string $id): self
    {
        return new self(RowActionKind::Detail, $dataTable, $id);
    }

    public static function editFormView(string $dataTable, int|string $id): self
    {
        return new self(RowActionKind::EditFormView, $dataTable, $id);
    }

    public static function editFormSubmit(string $dataTable, int|string $id): self
    {
        return new self(RowActionKind::EditFormSubmit, $dataTable, $id);
    }

    /**
     * @param int|string      $targetId the id of the entity the switch changes
     * @param int|string|null $rowId    the table row the switch sits in
     */
    public static function toggle(string $dataTable, string $field, int|string $targetId, int|string|null $rowId = null): self
    {
        return new self(RowActionKind::Toggle, $dataTable, $targetId, $field, $rowId);
    }
}
