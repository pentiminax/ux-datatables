<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class InvalidBulkSelectionException extends MutationException
{
    public static function selectAllForbidden(): self
    {
        return new self('This table only allows selecting one page at a time.');
    }

    public static function selectAllUnsupported(): self
    {
        return new self('This table cannot resolve a selection across every matching row.');
    }

    public static function emptySelection(): self
    {
        return new self('No row is selected.');
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
