<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\NormalizedSearchColumnInterface;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\DateSearchTerm;
use Pentiminax\UX\DataTables\Query\LikeValueEscaper;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\Strategy\ComparisonSearchStrategy;

/**
 * Applies the SearchBuilder tree as one AND-composed predicate.
 *
 * A tree naming an unknown or non-searchable column is ignored whole. A criterion whose value or
 * field cannot be queried matches nothing, and negated conditions keep NULL rows as the browser does.
 */
final class SearchBuilderFilter implements QueryFilterInterface
{
    private const string NO_MATCH = '1 = 0';

    private const array OPERATORS = ['=' => '=', '<' => '<', '<=' => '<=', '>=' => '>=', '>' => '>'];

    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        $tree = $context->intent->searchBuilder;
        if (null === $tree || !$this->resolvable($tree, $context)) {
            return;
        }

        $qb->andWhere($this->group($qb, $context, $tree));
    }

    /**
     * @param array<string, mixed> $group
     */
    private function resolvable(array $group, QueryFilterContext $context): bool
    {
        foreach ($group['criteria'] as $node) {
            $known = isset($node['criteria']) ? $this->resolvable($node, $context) : null !== $this->column($context, $node['column']);
            if (!$known) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function group(QueryBuilder $qb, QueryFilterContext $context, array $group): string
    {
        $parts = [];
        foreach ($group['criteria'] as $node) {
            $parts[] = isset($node['criteria']) ? $this->group($qb, $context, $node) : $this->criterion($qb, $context, $node);
        }

        return '('.implode('OR' === $group['logic'] ? ' OR ' : ' AND ', $parts).')';
    }

    /**
     * @param array<string, mixed> $node
     */
    private function criterion(QueryBuilder $qb, QueryFilterContext $context, array $node): string
    {
        $column = $this->column($context, $node['column']);
        if (null === $column) {
            return self::NO_MATCH;
        }

        RelationFieldResolver::applySearchJoins($qb, $column);

        $path = RelationFieldResolver::resolveSearchField($column);
        if (null === $path || !RelationFieldResolver::supportsSearchFiltering($qb, $path)) {
            return self::NO_MATCH;
        }

        $field   = RelationFieldResolver::resolve($qb, $context->alias, $path);
        $negated = str_starts_with($node['condition'], '!');
        $base    = $negated ? substr($node['condition'], 1) : $node['condition'];

        if ('null' === $base) {
            return $this->nullness($qb, $column, $path, $field, $negated);
        }

        $positive = $this->positive($qb, $context, $column, $path, $field, $base, $node['values']);
        if (null === $positive) {
            return self::NO_MATCH;
        }

        return $negated ? \sprintf('(%s IS NULL OR NOT (%s))', $field, $positive) : $positive;
    }

    private function nullness(QueryBuilder $qb, ColumnInterface $column, string $path, string $field, bool $negated): string
    {
        $isNull = RelationFieldResolver::supportsTextSearch($qb, $path) && !$column->isNumber() && !$column->isDate()
            ? \sprintf("(%s IS NULL OR %s = '')", $field, $field)
            : \sprintf('%s IS NULL', $field);

        return $negated ? \sprintf('NOT %s', $isNull) : $isNull;
    }

    /**
     * @param list<string> $values
     */
    private function positive(QueryBuilder $qb, QueryFilterContext $context, ColumnInterface $column, string $path, string $field, string $base, array $values): ?string
    {
        $dateType = RelationFieldResolver::resolveDateFieldType($qb, $path);
        if (null !== $dateType && ('between' === $base || isset(self::OPERATORS[$base]))) {
            return $this->dateRange($qb, $context, $field, $dateType, $base, $values);
        }

        if ('between' === $base) {
            [$low, $type] = ComparisonSearchStrategy::resolveBindValue($qb, $path, $values[0]);
            [$high]       = ComparisonSearchStrategy::resolveBindValue($qb, $path, $values[1]);
            if (null === $low || null === $high) {
                return null;
            }
            if ($low > $high) {
                [$low, $high] = [$high, $low];
            }

            return \sprintf('%s BETWEEN %s AND %s', $field, $this->bind($qb, $context, $low, $type), $this->bind($qb, $context, $high, $type));
        }

        if (isset(self::OPERATORS[$base])) {
            [$value, $type] = ComparisonSearchStrategy::resolveBindValue($qb, $path, $values[0]);

            return null === $value ? null : \sprintf('%s %s %s', $field, self::OPERATORS[$base], $this->bind($qb, $context, $value, $type));
        }

        if (!RelationFieldResolver::supportsTextSearch($qb, $path)) {
            return null;
        }

        $normalize = !$column instanceof NormalizedSearchColumnInterface || $column->isSearchNormalized();
        $needle    = LikeValueEscaper::escape($normalize ? mb_strtolower($values[0]) : $values[0]);
        $pattern   = match ($base) {
            'starts' => $needle.'%',
            'ends'   => '%'.$needle,
            default  => '%'.$needle.'%',
        };

        return \sprintf("%s LIKE %s ESCAPE '%s'", $normalize ? \sprintf('LOWER(%s)', $field) : $field, $this->bind($qb, $context, $pattern, null), LikeValueEscaper::ESCAPE_CHARACTER);
    }

    /**
     * Dates compare by whole day, so a datetime field matches a date typed without a time.
     *
     * @param list<string> $values
     */
    private function dateRange(QueryBuilder $qb, QueryFilterContext $context, string $field, string $type, string $base, array $values): ?string
    {
        $days = [];
        foreach ('between' === $base ? [$values[0], $values[1]] : [$values[0]] as $value) {
            $date = DateSearchTerm::normalize($value);
            if (null === $date) {
                return null;
            }
            $days[] = $date->setTime(0, 0);
        }

        if (isset($days[1]) && $days[0] > $days[1]) {
            $days = array_reverse($days);
        }

        $start = $days[0];
        $end   = ($days[1] ?? $days[0])->modify('+1 day');

        return match ($base) {
            '<'     => \sprintf('%s < %s', $field, $this->bind($qb, $context, $start, $type)),
            '>'     => \sprintf('%s >= %s', $field, $this->bind($qb, $context, $end, $type)),
            '<='    => \sprintf('%s < %s', $field, $this->bind($qb, $context, $end, $type)),
            '>='    => \sprintf('%s >= %s', $field, $this->bind($qb, $context, $start, $type)),
            default => \sprintf('(%s >= %s AND %s < %s)', $field, $this->bind($qb, $context, $start, $type), $field, $this->bind($qb, $context, $end, $type)),
        };
    }

    private function bind(QueryBuilder $qb, QueryFilterContext $context, mixed $value, ?string $type): string
    {
        $param = \sprintf('search_builder_param_%d', $context->nextParamIndex());
        $qb->setParameter($param, $value, $type);

        return ':'.$param;
    }

    /**
     * SearchBuilder reports the column's `data` key as `origData`.
     */
    private function column(QueryFilterContext $context, string $key): ?ColumnInterface
    {
        foreach ($context->columns as $column) {
            if ($column->isSearchable() && ($column->getData() ?? $column->getName()) === $key) {
                return $column;
            }
        }

        $byName = $context->columns[$key] ?? null;

        return $byName?->isSearchable() ? $byName : null;
    }
}
