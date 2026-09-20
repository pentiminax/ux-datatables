<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

/**
 * Declare a user-facing filter on a property, a getter, or the table class itself.
 *
 * The signature matches {@see DataTableColumn} character for character: whoever knows one knows the
 * other. `type`, `name` and `position` drive the reader rather than the filter, which is why they
 * stay named parameters while everything else goes through `options`.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class DataTableFilter
{
    /**
     * @param ?class-string       $type     Filter class to build, guessed from the declared type when null
     * @param array<string,mixed> $options  Applied through the filter class own fluent API
     * @param ?string             $name     Filter key, defaulting to the member name and required at class level
     * @param ?int                $position Explicit ordering, ties falling back to declaration order
     */
    public function __construct(
        public readonly ?string $type = null,
        public readonly array $options = [],
        public readonly ?string $name = null,
        public readonly ?int $position = null,
    ) {
    }
}
