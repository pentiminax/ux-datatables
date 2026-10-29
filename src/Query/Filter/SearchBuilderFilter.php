<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\LikeValueEscaper;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\Strategy\ComparisonSearchStrategy;

/**
 * Applies the validated SearchBuilder tree as one AND-composed predicate.
 *
 * A criterion on an unknown, non-searchable or unmapped column, or one whose value cannot be
 * bound to the field type, is left out rather than crashing the Ajax request. Left out means
 * "no constraint": inside an OR group that widens the match, as it does for an incomplete
 * criterion in the SearchBuilder UI.
 */
final class SearchBuilderFilter implements QueryFilterInterface
{
    private const array OPERATORS = ['=' => '=', '!=' => '!=', '<' => '<', '<=' => '<=', '>=' => '>=', '>' => '>'];

    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        $tree = $context->intent->searchBuilder;
        if (null === $tree) {
            return;
        }

        $predicate = $this->group($qb, $context, $tree);
        if (null !== $predicate) {
            $qb->andWhere($predicate);
        }
    }

    /**
     * @param array<string, mixed> $group
     */
    private function group(QueryBuilder $qb, QueryFilterContext $context, array $group): ?string
    {
        $parts = [];
        foreach ($group['criteria'] as $node) {
            $part = isset($node['criteria']) ? $this->group($qb, $context, $node) : $this->criterion($qb, $context, $node);
            if (null !== $part) {
                $parts[] = $part;
            }
        }

        if ([] === $parts) {
            return null;
        }

        return '('.implode('OR' === $group['logic'] ? ' OR ' : ' AND ', $parts).')';
    }

    /**
     * @param array<string, mixed> $node
     */
    private function criterion(QueryBuilder $qb, QueryFilterContext $context, array $node): ?string
    {
        $column = $this->column($context, $node['column']);
        if (null === $column) {
            return null;
        }

        RelationFieldResolver::applySearchJoins($qb, $column);

        $path = RelationFieldResolver::resolveSearchField($column);
        if (null === $path || !RelationFieldResolver::supportsSearchFiltering($qb, $path)) {
            return null;
        }

        $field     = RelationFieldResolver::resolve($qb, $context->alias, $path);
        $condition = $node['condition'];
        $values    = $node['values'];
        $text      = RelationFieldResolver::supportsTextSearch($qb, $path);

        if ('null' === $condition || '!null' === $condition) {
            $isNull = $text && !$column->isNumber() && !$column->isDate()
                ? \sprintf("(%s IS NULL OR %s = '')", $field, $field)
                : \sprintf('%s IS NULL', $field);

            return 'null' === $condition ? $isNull : \sprintf('NOT %s', $isNull);
        }

        if (str_contains($condition, 'between')) {
            return $this->between($qb, $context, $path, $field, $values, str_starts_with($condition, '!'));
        }

        if (isset(self::OPERATORS[$condition])) {
            [$value, $type] = ComparisonSearchStrategy::resolveBindValue($qb, $path, $values[0]);
            if (null === $value) {
                return null;
            }

            return $this->bind($qb, $context, $field, self::OPERATORS[$condition], $value, $type);
        }

        if (!$text) {
            return null;
        }

        $negated = str_starts_with($condition, '!');
        $kind    = ltrim($condition, '!');
        $needle  = LikeValueEscaper::escape(mb_strtolower($values[0]));
        $pattern = match ($kind) {
            'starts' => $needle.'%',
            'ends'   => '%'.$needle,
            default  => '%'.$needle.'%',
        };

        $param = $this->param($context);
        $qb->setParameter($param, $pattern);

        return \sprintf("LOWER(%s) %s :%s ESCAPE '%s'", $field, $negated ? 'NOT LIKE' : 'LIKE', $param, LikeValueEscaper::ESCAPE_CHARACTER);
    }

    /**
     * @param list<string> $values
     */
    private function between(QueryBuilder $qb, QueryFilterContext $context, string $path, string $field, array $values, bool $negated): ?string
    {
        [$low, $type] = ComparisonSearchStrategy::resolveBindValue($qb, $path, $values[0]);
        [$high]       = ComparisonSearchStrategy::resolveBindValue($qb, $path, $values[1]);
        if (null === $low || null === $high) {
            return null;
        }

        $lowParam  = $this->param($context);
        $highParam = $this->param($context);
        $qb->setParameter($lowParam, $low, $type);
        $qb->setParameter($highParam, $high, $type);

        return \sprintf('%s %sBETWEEN :%s AND :%s', $field, $negated ? 'NOT ' : '', $lowParam, $highParam);
    }

    private function bind(QueryBuilder $qb, QueryFilterContext $context, string $field, string $operator, mixed $value, ?string $type): string
    {
        $param = $this->param($context);
        $qb->setParameter($param, $value, $type);

        return \sprintf('%s %s :%s', $field, $operator, $param);
    }

    private function param(QueryFilterContext $context): string
    {
        return \sprintf('search_builder_param_%d', $context->nextParamIndex());
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
