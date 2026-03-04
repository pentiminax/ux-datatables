<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class ChoiceColumn extends AbstractColumn
{
    public const string OPTION_CHOICES               = 'choices';
    public const string OPTION_RENDER_AS_BADGES      = 'renderAsBadges';
    public const string OPTION_DEFAULT_BADGE_VARIANT = 'defaultBadgeVariant';

    public const array VALID_BADGE_TYPES = ['success', 'warning', 'danger', 'info', 'primary', 'secondary', 'light', 'dark'];

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML);
    }

    /**
     * @param array<string|int, string>|list<\BackedEnum>|class-string<\BackedEnum> $choices
     */
    public function setChoices(array|string $choices): self
    {
        if (\is_string($choices)) {
            if (!is_a($choices, \BackedEnum::class, true)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not a BackedEnum class.', $choices));
            }

            $this->setCustomOption(self::OPTION_CHOICES, $this->normalizeBackedEnumChoices($choices::cases()));

            return $this;
        }

        if ($this->isBackedEnumList($choices)) {
            $choices = $this->normalizeBackedEnumChoices($choices);
        }

        $this->setCustomOption(self::OPTION_CHOICES, $choices);

        return $this;
    }

    public function renderAsBadges(array|bool $badgeSelector = [], string $defaultVariant = 'secondary'): self
    {
        if (false === $badgeSelector) {
            $this->setCustomOption(self::OPTION_RENDER_AS_BADGES, null);
            $this->setCustomOption(self::OPTION_DEFAULT_BADGE_VARIANT, null);

            return $this;
        }

        if (true === $badgeSelector) {
            $badgeSelector = [];
        }

        if (\is_array($badgeSelector)) {
            foreach ($badgeSelector as $badgeType) {
                $this->assertValidBadgeType($badgeType, 'The values of the array passed to the "%s" method must be one of the following valid badge types: "%s" ("%s" given).');
            }
        }

        $this->assertValidBadgeType($defaultVariant, 'The default variant passed to the "%s" method must be one of the following valid badge types: "%s" ("%s" given).');

        $this->setCustomOption(self::OPTION_RENDER_AS_BADGES, $badgeSelector);
        $this->setCustomOption(self::OPTION_DEFAULT_BADGE_VARIANT, $defaultVariant);

        return $this;
    }

    /**
     * @param list<\BackedEnum> $choices
     *
     * @return array<string, string>
     */
    private function normalizeBackedEnumChoices(array $choices): array
    {
        $map = [];

        foreach ($choices as $case) {
            $map[(string) $case->value] = $case->name;
        }

        return $map;
    }

    /**
     * @param array<mixed> $choices
     */
    private function isBackedEnumList(array $choices): bool
    {
        if ([] === $choices || !array_is_list($choices)) {
            return false;
        }

        foreach ($choices as $choice) {
            if (!$choice instanceof \BackedEnum) {
                return false;
            }
        }

        return true;
    }

    private function assertValidBadgeType(string $badgeType, string $message): void
    {
        if (\in_array($badgeType, self::VALID_BADGE_TYPES, true)) {
            return;
        }

        throw new \InvalidArgumentException(\sprintf($message, self::class.'::renderAsBadges', implode(', ', self::VALID_BADGE_TYPES), $badgeType));
    }
}
