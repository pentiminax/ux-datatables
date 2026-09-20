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
     * @return class-string<AbstractFilter>
     */
    public function mapType(?\ReflectionNamedType $type): string
    {
        if (null === $type) {
            return TextFilter::class;
        }

        $typeName = $type->getName();

        return match (true) {
            'bool' === $typeName                                                    => TernaryFilter::class,
            !$type->isBuiltin() && is_a($typeName, \BackedEnum::class, true)        => ChoiceFilter::class,
            !$type->isBuiltin() && is_a($typeName, \DateTimeInterface::class, true) => DateRangeFilter::class,
            default                                                                 => TextFilter::class,
        };
    }
}
