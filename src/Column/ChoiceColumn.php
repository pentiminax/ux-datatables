<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Model\EnumChoices;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    /** @var array<string, TranslatableInterface> value => translatable enum case */
    private array $translatableCases = [];

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
     * case name. Cases of an enum implementing `TranslatableInterface` are translated at
     * render time instead, in the locale of the current request.
     *
     * @param array<string|int, string|int>|list<\BackedEnum>|class-string<\BackedEnum> $choices
     */
    public function setChoices(array|string $choices): static
    {
        $this->translatableCases = [];

        if (\is_string($choices)) {
            if (!is_a($choices, \BackedEnum::class, true)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not a BackedEnum class.', $choices));
            }

            return $this->setEnumChoices($choices::cases());
        }

        if (EnumChoices::isList($choices)) {
            return $this->setEnumChoices($choices);
        }

        $this->setCustomOption(self::OPTION_CHOICES, $this->normalizeArrayChoices($choices));

        return $this;
    }

    /**
     * Replace the labels of translatable enum cases with their translation.
     *
     * Called by the RenderingPreparer when a translator is available, so the label is resolved
     * at render time rather than when the column is configured.
     */
    public function translateLabels(TranslatorInterface $translator, ?string $locale = null): void
    {
        if ([] === $this->translatableCases) {
            return;
        }

        $choices = $this->getCustomOptions()[self::OPTION_CHOICES] ?? [];

        foreach ($this->translatableCases as $value => $case) {
            $choices[$value] = $case->trans($translator, $locale);
        }

        $this->setCustomOption(self::OPTION_CHOICES, $choices);
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
    public function renderAsBadges(array|bool $badgeSelector = [], string $defaultVariant = 'secondary'): static
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
     * @param list<\BackedEnum> $cases
     */
    private function setEnumChoices(array $cases): static
    {
        $this->translatableCases = EnumChoices::translatableCases($cases);

        return $this->setCustomOption(self::OPTION_CHOICES, EnumChoices::labels($cases));
    }

    private function assertValidBadgeType(string $badgeType, string $message): void
    {
        if (\in_array($badgeType, self::VALID_BADGE_TYPES, true)) {
            return;
        }

        throw new \InvalidArgumentException(\sprintf($message, self::class.'::renderAsBadges', implode(', ', self::VALID_BADGE_TYPES), $badgeType));
    }
}
