<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum ColumnControlLogic: string
{
    case Contains       = 'contains';
    case Empty          = 'empty';
    case Ends           = 'ends';
    case Equal          = 'equal';
    case Greater        = 'greater';
    case GreaterOrEqual = 'greaterOrEqual';
    case In             = 'in';
    case Less           = 'less';
    case LessOrEqual    = 'lessOrEqual';
    case NotContains    = 'notContains';
    case NotEmpty       = 'notEmpty';
    case NotEqual       = 'notEqual';
    case Starts         = 'starts';

    public function supportsComparisonStrategy(): bool
    {
        return match ($this) {
            self::Ends,
            self::Equal,
            self::Greater,
            self::GreaterOrEqual,
            self::Less,
            self::LessOrEqual,
            self::NotContains,
            self::NotEqual,
            self::Starts => true,
            default      => false,
        };
    }

    /**
     * Returns true for the logics that ComparisonSearchStrategy expresses with
     * UX_DATATABLES_SEARCH rather than a raw SQL comparison operator.
     *
     * Contains is intentionally excluded here because it is dispatched through
     * ContainsSearchStrategy → SearchPredicateFactory → SearchConditionBuilder,
     * which already emits UX_DATATABLES_SEARCH.
     */
    public function usesTextSearch(): bool
    {
        return match ($this) {
            self::Ends,
            self::NotContains,
            self::Starts => true,
            default      => false,
        };
    }

    /**
     * Whether the logic is expressed with SQL LIKE, which strict engines reject on
     * non-text columns such as PostgreSQL's native uuid type.
     *
     * Includes Contains in addition to the ComparisonSearchStrategy text-search logics.
     */
    public function usesLikeOperator(): bool
    {
        return match ($this) {
            self::Contains,
            self::Ends,
            self::NotContains,
            self::Starts => true,
            default      => false,
        };
    }

    public function operator(): string
    {
        if (!$this->supportsComparisonStrategy()) {
            throw new \LogicException(\sprintf('Logic "%s" is not compatible with comparison strategy.', $this->value));
        }

        return match ($this) {
            self::Ends           => 'LIKE',
            self::Equal          => '=',
            self::Greater        => '>',
            self::GreaterOrEqual => '>=',
            self::Less           => '<',
            self::LessOrEqual    => '<=',
            self::NotContains    => 'NOT LIKE',
            self::NotEqual       => '!=',
            self::Starts         => 'LIKE',
        };
    }

    public function paramFormat(): string
    {
        if (!$this->supportsComparisonStrategy()) {
            throw new \LogicException(\sprintf('Logic "%s" is not compatible with comparison strategy.', $this->value));
        }

        return match ($this) {
            self::Ends           => '%%%s',
            self::Equal          => '%s',
            self::Greater        => '%s',
            self::GreaterOrEqual => '%s',
            self::Less           => '%s',
            self::LessOrEqual    => '%s',
            self::NotContains    => '%%%s%%',
            self::NotEqual       => '%s',
            self::Starts         => '%s%%',
        };
    }
}
