<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

/**
 * An attribute found on a class member, carried with what the member itself tells us.
 *
 * @template T of object
 */
final class AttributeTarget
{
    /**
     * @param T                     $attribute
     * @param string                $name             Member name, before the attribute gets a chance to override it
     * @param ?\ReflectionNamedType $type             Declared type, used to guess what to build when the attribute stays silent
     * @param int                   $declarationOrder Source order, which breaks ties between equal positions
     */
    public function __construct(
        public readonly object $attribute,
        public readonly string $name,
        public readonly ?\ReflectionNamedType $type,
        public readonly int $declarationOrder,
    ) {
    }
}
