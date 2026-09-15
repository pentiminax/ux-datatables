<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions\ColumnControl;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final class SearchListOptionsNormalizer
{
    /**
     * @param array<mixed>|class-string<\BackedEnum> $options
     *
     * @return list<array{label: string, value: scalar}>
     */
    public static function normalize(array|string $options, ?TranslatorInterface $translator = null): array
    {
        if (\is_string($options)) {
            if (!is_a($options, \BackedEnum::class, true)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not a BackedEnum class.', $options));
            }

            $options = $options::cases();
        }

        return self::normalizeArray($options, $translator);
    }

    /**
     * @param iterable<mixed> $options
     *
     * @return list<array{label: string, value: scalar}>
     */
    public static function normalizeIterable(iterable $options, ?TranslatorInterface $translator = null): array
    {
        return self::normalizeArray(
            \is_array($options) ? $options : iterator_to_array($options),
            $translator,
        );
    }

    /**
     * @param array<mixed> $options
     *
     * @return list<array{label: string, value: scalar}>
     */
    private static function normalizeArray(array $options, ?TranslatorInterface $translator): array
    {
        $normalized = [];
        $isList     = array_is_list($options);

        foreach ($options as $label => $option) {
            if (!$isList && \is_scalar($option)) {
                $normalized[] = ['label' => (string) $label, 'value' => $option];

                continue;
            }

            if ($option instanceof \BackedEnum) {
                $normalized[] = [
                    'label' => self::enumLabel($option, $translator),
                    'value' => $option->value,
                ];

                continue;
            }

            if (\is_scalar($option)) {
                $normalized[] = ['label' => (string) $option, 'value' => $option];

                continue;
            }

            if (
                \is_array($option)
                && \array_key_exists('label', $option)
                && \array_key_exists('value', $option)
                && \is_scalar($option['label'])
                && \is_scalar($option['value'])
            ) {
                $normalized[] = [
                    'label' => (string) $option['label'],
                    'value' => $option['value'],
                ];

                continue;
            }

            self::throwInvalidOptions();
        }

        return $normalized;
    }

    private static function enumLabel(\BackedEnum $case, ?TranslatorInterface $translator): string
    {
        if (null !== $translator && $case instanceof TranslatableInterface) {
            return $case->trans($translator);
        }

        if (method_exists($case, 'getLabel')) {
            return (string) $case->getLabel();
        }

        if (method_exists($case, 'label')) {
            return (string) $case->label();
        }

        return $case->name;
    }

    private static function throwInvalidOptions(): never
    {
        throw new \InvalidArgumentException('Search list options must contain scalar values, BackedEnum cases, or label/value arrays.');
    }
}
