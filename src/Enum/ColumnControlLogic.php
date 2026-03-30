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
