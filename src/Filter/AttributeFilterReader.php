<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Pentiminax\UX\DataTables\Attribute\AttributeTarget;
use Pentiminax\UX\DataTables\Attribute\DataTableFilter;
use Pentiminax\UX\DataTables\Attribute\MemberAttributeReader;
use Pentiminax\UX\DataTables\Attribute\OptionApplier;

/**
 * The filter counterpart of {@see \Pentiminax\UX\DataTables\Column\AttributeColumnReader}, built on
 * the same generic reader and the same option applier.
 */
final class AttributeFilterReader
{
    private readonly OptionApplier $optionApplier;

    public function __construct(
        private readonly FilterTypeMapper $filterTypeMapper = new FilterTypeMapper(),
        private readonly MemberAttributeReader $memberAttributeReader = new MemberAttributeReader(),
    ) {
        $this->optionApplier = new OptionApplier([
            // values() takes the two states as separate arguments, which no option key can spell.
            'values' => static function (TernaryFilter $filter, array $values): void {
                if (!\array_key_exists(0, $values) || !\array_key_exists(1, $values)) {
                    throw new \InvalidArgumentException('The "values" option of a ternary filter takes the true value and the false value, in that order.');
                }

                $filter->values($values[0], $values[1]);
            },
        ]);
    }

    /**
     * Filters declared on the members of the class holding the data.
     *
     * @param class-string $dataClass
     *
     * @return AbstractFilter[]
     */
    public function readFilters(string $dataClass): array
    {
        return $this->buildFilters($this->memberAttributeReader->readMembers($dataClass, DataTableFilter::class));
    }

    /**
     * Filters declared on a table class, for the ones no member of the data class backs.
     *
     * @param class-string $dataTableClass
     *
     * @return AbstractFilter[]
     */
    public function readClassFilters(string $dataTableClass): array
    {
        return $this->buildFilters($this->memberAttributeReader->readClass($dataTableClass, DataTableFilter::class));
    }

    /**
     * @param list<AttributeTarget<DataTableFilter>> $targets
     *
     * @return AbstractFilter[]
     */
    private function buildFilters(array $targets): array
    {
        usort($targets, static function (AttributeTarget $a, AttributeTarget $b): int {
            $positionA = $a->attribute->position ?? 0;
            $positionB = $b->attribute->position ?? 0;

            return $positionA !== $positionB ? $positionA <=> $positionB : $a->declarationOrder <=> $b->declarationOrder;
        });

        $filters = array_map($this->buildFilter(...), $targets);

        $this->assertUniqueNames($filters);

        return $filters;
    }

    /**
     * The collection is keyed by name, so a second filter claiming a name would silently replace the
     * first rather than render beside it.
     *
     * @param AbstractFilter[] $filters
     */
    private function assertUniqueNames(array $filters): void
    {
        $seen = [];

        foreach ($filters as $filter) {
            $name = $filter->getName();

            if (isset($seen[$name])) {
                throw new \InvalidArgumentException(\sprintf('Two filters are declared under the name "%s".', $name));
            }

            $seen[$name] = true;
        }
    }

    /**
     * @param AttributeTarget<DataTableFilter> $target
     */
    private function buildFilter(AttributeTarget $target): AbstractFilter
    {
        $attribute = $target->attribute;

        $name = $attribute->name ?? $target->name;

        if ('' === $name) {
            throw new \InvalidArgumentException(\sprintf('A "%s" attribute declared on a class must carry a name, since no member gives it one.', DataTableFilter::class));
        }

        $filterClass = $attribute->type ?? $this->filterTypeMapper->mapType($target->type);

        $this->assertBuildable($filterClass, $name);

        /** @var AbstractFilter $filter */
        $filter = $filterClass::new($name);

        $this->configureFromDeclaredType($filter, $target->type, null === $attribute->type);

        $this->optionApplier->apply($filter, $attribute->options);

        return $filter;
    }

    /**
     * A filter built from an attribute never gets a query() closure, so a filter class that has no
     * default condition could only fail on the first request that submits it.
     *
     * @param class-string $filterClass
     */
    private function assertBuildable(string $filterClass, string $name): void
    {
        if (!is_a($filterClass, AbstractFilter::class, true)) {
            throw new \InvalidArgumentException(\sprintf('The type "%s" declared for filter "%s" must be a class extending "%s".', $filterClass, $name, AbstractFilter::class));
        }

        if (CheckboxFilter::class === $filterClass) {
            throw new \InvalidArgumentException(\sprintf('The filter "%s" cannot be declared through an attribute: "%s" has no default condition and needs the query() closure only configureFilters() can give it.', $name, CheckboxFilter::class));
        }
    }

    /**
     * What the member declares already answers part of what the filter needs, and leaving it out
     * produces a filter that runs without filtering anything right. An explicit option still wins,
     * since the applier runs afterwards.
     *
     * A ternary filter defaults to IS NULL / IS NOT NULL, which on a boolean field matches every row
     * for "true" and none for "false". A choice filter left without options renders an empty select.
     *
     * Only the guessed ternary gets its states: values() flips what the query means and no option can
     * undo it, so a filter someone typed out by hand keeps the class default. Filling a choice
     * filter's options is additive either way, and an explicit "options" entry still wins.
     *
     * @param bool $guessed Whether the filter class was guessed rather than named by the attribute
     */
    private function configureFromDeclaredType(AbstractFilter $filter, ?\ReflectionNamedType $type, bool $guessed): void
    {
        if (null === $type) {
            return;
        }

        if ($guessed && $filter instanceof TernaryFilter && 'bool' === $type->getName()) {
            $filter->values(true, false);

            return;
        }

        if ($filter instanceof ChoiceFilter && !$type->isBuiltin() && is_a($type->getName(), \BackedEnum::class, true)) {
            $filter->options($type->getName());
        }
    }
}
