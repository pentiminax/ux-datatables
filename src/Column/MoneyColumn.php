<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class MoneyColumn extends AbstractColumn
{
    public const string OPTION_IS_MONEY        = 'isMoney';
    public const string OPTION_CURRENCY        = 'currency';
    public const string OPTION_STORED_AS_CENTS = 'storedAsCents';
    public const string OPTION_DECIMALS        = 'decimals';

    private const int MIN_DECIMALS = 0;
    private const int MAX_DECIMALS = 20;

    public static function new(string $name, string $title = ''): static
    {
        $column = static::createWithType($name, $title, ColumnType::NUM);
        $column->setCustomOption(self::OPTION_IS_MONEY, true);
        $column->setCustomOption(self::OPTION_CURRENCY, 'EUR');
        $column->setCustomOption(self::OPTION_STORED_AS_CENTS, true);
        $column->setCustomOption(self::OPTION_DECIMALS, 2);

        return $column;
    }

    public function currency(string $currency): static
    {
        return $this->setCurrency($currency);
    }

    public function setCurrency(string $currency): static
    {
        $currency = strtoupper($currency);

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException(\sprintf('The currency "%s" is not a valid ISO 4217 currency code.', $currency));
        }

        $this->setCustomOption(self::OPTION_CURRENCY, $currency);

        return $this;
    }

    public function storedAsCents(bool $storedAsCents = true): static
    {
        return $this->setStoredAsCents($storedAsCents);
    }

    public function setStoredAsCents(bool $storedAsCents = true): static
    {
        $this->setCustomOption(self::OPTION_STORED_AS_CENTS, $storedAsCents);

        return $this;
    }

    public function decimals(int $decimals): static
    {
        return $this->setNumDecimals($decimals);
    }

    public function setNumDecimals(int $decimals): static
    {
        if ($decimals < self::MIN_DECIMALS || $decimals > self::MAX_DECIMALS) {
            throw new \InvalidArgumentException(\sprintf('The number of decimals must be between %d and %d.', self::MIN_DECIMALS, self::MAX_DECIMALS));
        }

        $this->setCustomOption(self::OPTION_DECIMALS, $decimals);

        return $this;
    }
}
