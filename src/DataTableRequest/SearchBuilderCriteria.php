<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataTableRequest;

/**
 * Strict parser for the `searchBuilder` request parameter.
 *
 * The payload is client controlled, so it is accepted whole or not at all: any unknown logic or
 * condition, a malformed node, or a tree beyond the depth or size limits yields null and no
 * SearchBuilder filter is applied. Criteria that are merely incomplete (a blank value) are
 * dropped, as SearchBuilder itself ignores them.
 *
 * A group is `['logic' => 'AND'|'OR', 'criteria' => list<node>]`; a criterion is
 * `['column' => string, 'condition' => string, 'type' => string, 'values' => list<string>]`.
 */
final class SearchBuilderCriteria
{
    public const int MAX_DEPTH = 5;

    public const int MAX_NODES = 50;

    private const array CONDITIONS = [
        '=', '!=', '<', '<=', '>=', '>', 'between', '!between', 'null', '!null',
        'starts', '!starts', 'contains', '!contains', 'ends', '!ends',
    ];

    private const int MAX_STRING = 255;

    /**
     * @return array{logic: string, criteria: list<array<string, mixed>>}|null
     */
    public static function parse(mixed $raw): ?array
    {
        if (!\is_array($raw) || [] === $raw) {
            return null;
        }

        $nodes = 0;
        $group = self::group($raw, 1, $nodes);

        return null !== $group && [] !== $group['criteria'] ? $group : null;
    }

    /**
     * @return array{logic: string, criteria: list<array<string, mixed>>}|null
     */
    private static function group(array $raw, int $depth, int &$nodes): ?array
    {
        $logic    = $raw['logic']    ?? 'AND';
        $children = $raw['criteria'] ?? null;

        if ($depth > self::MAX_DEPTH || !\in_array($logic, ['AND', 'OR'], true) || !\is_array($children) || !array_is_list($children)) {
            return null;
        }

        $criteria = [];
        foreach ($children as $child) {
            if (++$nodes > self::MAX_NODES || !\is_array($child)) {
                return null;
            }

            if (isset($child['criteria'])) {
                $node = self::group($child, $depth + 1, $nodes);
                if (null === $node) {
                    return null;
                }
                if ([] !== $node['criteria']) {
                    $criteria[] = $node;
                }

                continue;
            }

            $node = self::criterion($child);
            if (null === $node) {
                return null;
            }
            if (false !== $node) {
                $criteria[] = $node;
            }
        }

        return ['logic' => $logic, 'criteria' => $criteria];
    }

    /**
     * @return array<string, mixed>|false|null null when invalid, false when incomplete
     */
    private static function criterion(array $raw): array|false|null
    {
        $column    = $raw['origData']  ?? null;
        $condition = $raw['condition'] ?? null;
        $type      = $raw['type']      ?? '';
        $values    = $raw['value']     ?? [];

        if (!self::text($column) || !\is_string($condition) || !\in_array($condition, self::CONDITIONS, true)
                                 || !\is_string($type) || \strlen($type) > self::MAX_STRING || !\is_array($values) || \count($values) > 2) {
            return null;
        }

        foreach ($values as $value) {
            if (!\is_scalar($value) || !self::text((string) $value, true)) {
                return null;
            }
        }

        $values   = array_map(strval(...), array_values($values));
        $required = match ($condition) {
            'null', '!null'       => 0,
            'between', '!between' => 2,
            default               => 1,
        };

        if (\count(array_filter($values, static fn (string $v): bool => '' !== $v)) < $required || \count($values) < $required) {
            return false;
        }

        return ['column' => $column, 'condition' => $condition, 'type' => $type, 'values' => $values];
    }

    private static function text(mixed $value, bool $allowEmpty = false): bool
    {
        return \is_string($value) && \strlen($value) <= self::MAX_STRING && ($allowEmpty || '' !== $value);
    }
}
