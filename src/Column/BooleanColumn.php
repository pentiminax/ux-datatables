<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class BooleanColumn extends AbstractColumn
{
    public const DISPLAY_AS_BADGE   = 'badge';
    public const DISPLAY_AS_TOGGLE  = 'toggle';
    public const OPTION_DISPLAY_AS  = 'booleanDisplayAs';
    public const OPTION_TRUE_LABEL  = 'booleanTrueLabel';
    public const OPTION_FALSE_LABEL = 'booleanFalseLabel';

    public static function new(string $name, string $title = ''): self
    {
        return static::createWithType($name, $title, ColumnType::NUM)
            ->displayAs(self::DISPLAY_AS_BADGE)
            ->setLabels('Yes', 'No');
    }

    public function displayAs(string $displayMode): self
    {
        if (!\in_array($displayMode, [self::DISPLAY_AS_BADGE, self::DISPLAY_AS_TOGGLE], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid display mode "%s". Allowed values are "badge" and "toggle".', $displayMode));
        }

        $this->setCustomOption(self::OPTION_DISPLAY_AS, $displayMode);

        return $this;
    }

    public function setLabels(string $trueLabel, string $falseLabel): self
    {
        $this->setCustomOption(self::OPTION_TRUE_LABEL, $trueLabel);
        $this->setCustomOption(self::OPTION_FALSE_LABEL, $falseLabel);

        return $this;
    }

    public function getDisplayMode(): string
    {
        return $this->getCustomOption(self::OPTION_DISPLAY_AS) ?? self::DISPLAY_AS_BADGE;
    }

    public function getTrueLabel(): string
    {
        return $this->getCustomOption(self::OPTION_TRUE_LABEL) ?? 'Yes';
    }

    public function getFalseLabel(): string
    {
        return $this->getCustomOption(self::OPTION_FALSE_LABEL) ?? 'No';
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            self::OPTION_DISPLAY_AS  => $this->getDisplayMode(),
            self::OPTION_TRUE_LABEL  => $this->getTrueLabel(),
            self::OPTION_FALSE_LABEL => $this->getFalseLabel(),
        ]);
    }
}
