<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

use Pentiminax\UX\DataTables\Column\AbstractColumn;

/**
 * Declares a column on the member it annotates, or on a table class for a column no member backs.
 *
 * Options are open on purpose: they are resolved against the column class that ends up carrying
 * them, so a column type the bundle does not ship is configurable the same way the built-in ones
 * are.
 *
 * The class is not final so {@see Column} can extend it for the whole 1.x line.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class DataTableColumn
{
    /**
     * @param class-string<AbstractColumn>|null $type     Column class to build, guessed from the declared type when null
     * @param array<string, mixed>              $options  Applied through the column's own fluent API
     * @param ?string                           $name     Column key, defaulting to the member name and required on a class-level declaration
     * @param ?int                              $position Explicit ordering, ties broken by declaration order
     */
    public function __construct(
        public readonly ?string $type = null,
        public readonly array $options = [],
        public readonly ?string $name = null,
        public readonly ?int $position = null,
    ) {
    }
}
