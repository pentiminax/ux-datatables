<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\DateSearchTerm;
use Pentiminax\UX\DataTables\Query\LikeValueEscaper;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\UuidSearchTerm;

/**
 * Parameterized search strategy for simple comparison operators.
 *
 * Replaces individual strategy classes (Equal, NotEqual, StartsWith, etc.)
 * that differ only in their SQL operator and parameter wrapping format.
 */
final class ComparisonSearchStrategy implements SearchStrategyInterface
{
    public function __construct(
        private readonly ColumnControlLogic $logic,
    ) {
        if (!$this->logic->supportsComparisonStrategy()) {
            throw new \InvalidArgumentException(\sprintf('Logic "%s" is not compatible with %s.', $this->logic->value, self::class));
        }
    }

    public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        $fieldPath = $column->getField();
        if (null === $fieldPath) {
            return;
        }

        $usesLike = $this->logic->usesLikeOperator();

        if ($usesLike && !RelationFieldResolver::supportsTextSearch($qb, $fieldPath)) {
            return;
        }

        [$bindValue, $bindType] = $this->resolveBindValue($qb, $fieldPath, $search->value);

        if (null === $bindValue) {
            return;
        }

        $field     = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $paramName = \sprintf('column_control_param_%d', $paramIndex);

        $condition = \sprintf('%s %s :%s', $field, $this->logic->operator(), $paramName);
        if ($usesLike) {
            $condition .= \sprintf(" ESCAPE '%s'", LikeValueEscaper::ESCAPE_CHARACTER);
        }

        $qb->andWhere($condition);
        $qb->setParameter(
            $paramName,
            match (true) {
                $bindValue instanceof \DateTimeImmutable => $bindValue,
                $usesLike                                => \sprintf($this->logic->paramFormat(), LikeValueEscaper::escape($bindValue)),
                default                                  => \sprintf($this->logic->paramFormat(), $bindValue),
            },
            $bindType,
        );
    }

    public function getLogic(): string
    {
        return $this->logic->value;
    }

    /**
     * Resolves the value to bind and its Doctrine type hint, or [null, null] when the raw
     * search term cannot be bound to the field's actual column type. LIKE-incompatible types
     * (uuid, date, etc.) are already filtered above; this only normalizes the value for the
     * types that still need a specific Doctrine type on setParameter().
     *
     * @return array{0: string|\DateTimeImmutable|null, 1: ?string}
     */
    private function resolveBindValue(QueryBuilder $qb, string $fieldPath, string $rawValue): array
    {
        $uuidType = RelationFieldResolver::resolveUuidFieldType($qb, $fieldPath);
        if (null !== $uuidType) {
            return [UuidSearchTerm::normalize($rawValue, $uuidType), $uuidType];
        }

        $dateType = RelationFieldResolver::resolveDateFieldType($qb, $fieldPath);
        if (null !== $dateType) {
            return [DateSearchTerm::normalize($rawValue), $dateType];
        }

        return [$rawValue, null];
    }
}
