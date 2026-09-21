<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

final class FilterTypeMapper
{
    /**
     * PHP types no filter class can query. A text filter is the only fallback, and it skips numeric
     * Doctrine fields rather than emitting a LIKE those columns reject.
     *
     * @var list<string>
     */
    private const array UNGUESSABLE_TYPES = [
        'float',
        'int',
    ];

    /**
     * Map a PHP reflection type to the filter class that reads it.
     *
     * CheckboxFilter is never guessed: it has no default condition, so it only works with a query()
     * closure an attribute cannot carry.
     *
     * @param string $name Filter name, for the error message when the type cannot be guessed
     *
     * @return class-string<AbstractFilter>
     */
    public function mapType(?\ReflectionNamedType $type, string $name = ''): string
    {
        if (null === $type) {
            return TextFilter::class;
        }

        $typeName = $type->getName();

        $this->assertGuessable($typeName, $name);

        return match (true) {
            'bool' === $typeName                                                    => TernaryFilter::class,
            !$type->isBuiltin() && is_a($typeName, \BackedEnum::class, true)        => ChoiceFilter::class,
            !$type->isBuiltin() && is_a($typeName, \DateTimeInterface::class, true) => DateRangeFilter::class,
            default                                                                 => TextFilter::class,
        };
    }

    /**
     * Guessing a text filter for a number builds a control that renders and then filters nothing: the
     * filter runs, finds a numeric Doctrine field, and drops its condition. Failing here says so
     * while the container compiles instead of at the first request that submits the filter.
     */
    private function assertGuessable(string $typeName, string $name): void
    {
        if (!\in_array($typeName, self::UNGUESSABLE_TYPES, true)) {
            return;
        }

        throw new \InvalidArgumentException(\sprintf('The filter "%s" cannot be guessed from its "%s" type: a text filter skips numeric fields, so the filter would never match. Name a filter class on the attribute, or filter on a text field instead.', $name, $typeName));
    }
}
