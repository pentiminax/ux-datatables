<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * @internal
 */
final class EnumChoices
{
    /**
     * @param array<mixed> $choices
     */
    public static function isList(array $choices): bool
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

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return array<string, string> value => label
     */
    public static function labels(array $cases): array
    {
        $labels = [];

        foreach ($cases as $case) {
            $labels[(string) $case->value] = self::label($case);
        }

        return $labels;
    }

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return array<string, TranslatableInterface> value => case
     */
    public static function translatableCases(array $cases): array
    {
        $translatable = [];

        foreach ($cases as $case) {
            if ($case instanceof TranslatableInterface) {
                $translatable[(string) $case->value] = $case;
            }
        }

        return $translatable;
    }

    private static function label(\BackedEnum $case): string
    {
        if (method_exists($case, 'getLabel')) {
            return (string) $case->getLabel();
        }

        if (method_exists($case, 'label')) {
            return (string) $case->label();
        }

        return $case->name;
    }
}
