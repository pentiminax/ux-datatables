<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class ChoiceColumn extends AbstractColumn
{
    public const string OPTION_CHOICES               = 'choices';
    public const string OPTION_RENDER_AS_BADGES      = 'renderAsBadges';
    public const string OPTION_DEFAULT_BADGE_VARIANT = 'defaultBadgeVariant';

    /**
     * Semantic badge color variants mapped by the frontend column style adapters
     * (Bootstrap 5 or Tailwind utilities depending on the detected DataTables CSS framework).
     *
     * @var list<string>
     */
    public const array VALID_BADGE_TYPES = ['success', 'warning', 'danger', 'info', 'primary', 'secondary', 'light', 'dark'];

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML);
    }

    /**
     * Define the available choices.
     *
     * Accepts:
     *  - an associative array using the following convention `[label => value]`
     *    (keys are the human-readable labels, values are the stored values);
     *  - a list of BackedEnum cases (e.g. `MyEnum::cases()`);
     *  - a BackedEnum class-string (e.g. `MyEnum::class`).
     *
     * Choices are always stored internally as `[value => label]` so the frontend
     * renderer can resolve the label from the raw cell value. For enums, the label
     * is taken from a `getLabel()`/`label()` method when available, otherwise the
     * case name.
     *
     * @param array<string|int, string|int>|list<\BackedEnum>|class-string<\BackedEnum> $choices
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
            $this->setCustomOption(self::OPTION_CHOICES, $this->normalizeBackedEnumChoices($choices));

            return $this;
        }

        $this->setCustomOption(self::OPTION_CHOICES, $this->normalizeArrayChoices($choices));

        return $this;
    }

    /**
     * Enable badge rendering for choice labels.
     *
     * Variants are semantic names (`success`, `danger`, …) from {@see VALID_BADGE_TYPES}.
     * The Stimulus controller maps them to Bootstrap 5 or Tailwind classes via the
     * detected DataTables style framework — no Bootstrap-specific markup is required
     * in PHP.
     *
     * @param array<string, string>|bool $badgeSelector Per-value variant map, `true` to enable with defaults, or `false` to disable
     */
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
     * Invert the `[label => value]` convention into the internal
     * `[value => label]` map consumed by the frontend renderer.
     *
     * @param array<string|int, string|int> $choices
     *
     * @return array<string, string>
     */
    private function normalizeArrayChoices(array $choices): array
    {
        $map = [];

        foreach ($choices as $label => $value) {
            $map[(string) $value] = (string) $label;
        }

        return $map;
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
            $map[(string) $case->value] = $this->resolveEnumLabel($case);
        }

        return $map;
    }

    private function resolveEnumLabel(\BackedEnum $case): string
    {
        if (method_exists($case, 'getLabel')) {
            return (string) $case->getLabel();
        }

        if (method_exists($case, 'label')) {
            return (string) $case->label();
        }

        return $case->name;
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
