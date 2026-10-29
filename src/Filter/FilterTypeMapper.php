<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

final class FilterTypeMapper
{
    /**
     * Map a PHP reflection type to the filter class that reads it.
     *
     * CheckboxFilter is never guessed: it has no default condition, so it only works with a query()
     * closure an attribute cannot carry.
     *
     * @param string $name Unused
     *
     * @return class-string<AbstractFilter>
     *
     * @deprecated since 1.3, the $name argument is ignored and no longer needed. Removed in 2.0.
     */
    public function mapType(?\ReflectionNamedType $type, string $name = ''): string
    {
        if (null === $type) {
            return TextFilter::class;
        }

        $typeName = $type->getName();

        return match (true) {
            \in_array($typeName, ['int', 'float'], true)                            => NumberRangeFilter::class,
            'bool' === $typeName                                                    => TernaryFilter::class,
            !$type->isBuiltin() && is_a($typeName, \BackedEnum::class, true)        => ChoiceFilter::class,
            !$type->isBuiltin() && is_a($typeName, \DateTimeInterface::class, true) => DateRangeFilter::class,
            default                                                                 => TextFilter::class,
        };
    }
}
