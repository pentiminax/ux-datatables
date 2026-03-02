<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class ChoiceColumn extends AbstractColumn
{
    public const string OPTION_CHOICES          = 'choices';
    public const string OPTION_RENDER_AS_BADGES = 'renderAsBadges';

    public const array VALID_BADGE_TYPES = ['success', 'warning', 'danger', 'info', 'primary', 'secondary', 'light', 'dark'];

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML);
    }

    /**
     * @param array<string|int, string>|class-string<\BackedEnum> $choices
     */
    public function setChoices(array|string $choices): self
    {
        if (\is_string($choices)) {
            if (!is_a($choices, \BackedEnum::class, true)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not a BackedEnum class.', $choices));
            }

            $map = [];
            foreach ($choices::cases() as $case) {
                $map[(string) $case->value] = $case->name;
            }

            $this->setCustomOption(self::OPTION_CHOICES, $map);

            return $this;
        }

        $this->setCustomOption(self::OPTION_CHOICES, $choices);

        return $this;
    }

    public function renderAsBadges(array|bool $badgeSelector = []): self
    {
        if (\is_array($badgeSelector)) {
            foreach ($badgeSelector as $badgeType) {
                if (!\in_array($badgeType, self::VALID_BADGE_TYPES, true)) {
                    throw new \InvalidArgumentException(\sprintf('The values of the array passed to the "%s" method must be one of the following valid badge types: "%s" ("%s" given).', __METHOD__, implode(', ', self::VALID_BADGE_TYPES), $badgeType));
                }
            }
        }

        $this->setCustomOption(self::OPTION_RENDER_AS_BADGES, $badgeSelector);

        return $this;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            array_filter([
                self::OPTION_CHOICES => $this->getCustomOption(self::OPTION_CHOICES),
                self::OPTION_RENDER_AS_BADGES => $this->getCustomOption(self::OPTION_RENDER_AS_BADGES),
            ], static fn (mixed $value) => null !== $value && '' !== $value)
        );
    }
}
