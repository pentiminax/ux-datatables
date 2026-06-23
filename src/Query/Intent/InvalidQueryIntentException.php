<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

/**
 * Raised for impossible programmer/configuration states while building a {@see DataTableQueryIntent}.
 *
 * Examples: duplicate configured column names or entries that are not a ColumnInterface.
 * Malformed transport-level input (unknown request indexes, empty searches) is never an error;
 * it is dropped to preserve current no-op compatibility.
 */
final class InvalidQueryIntentException extends \InvalidArgumentException
{
}
