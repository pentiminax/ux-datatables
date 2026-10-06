<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class ColumnToFormTypeMapper
{
    /**
     * MoneyType converts through floats, exact for integers up to 2^53: more than eight decimals
     * would round the stored integer silently.
     */
    private const int MAX_MONEY_FORM_DECIMALS = 8;

    private readonly PropertyAccessorInterface $propertyAccessor;

    public function __construct(?PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param object|null $entity Edited entity, used to detect enum-backed properties and to skip
     *                            columns the entity cannot bind
     *
     * @return array{formType: class-string, options: array<string, mixed>}|null
     */
    public function map(ColumnInterface $column, ?object $entity = null): ?array
    {
        $customOptions = $column->getCustomOptions();

        if ($this->isSkippable($column, $customOptions, $entity)) {
            return null;
        }

        $options = [
            'label' => $column->getTitle() ?: $column->getName(),
        ];

        $enumClass = $this->resolveEnumClass($entity, $column->getName());

        if (null !== $enumClass) {
            return [
                'formType' => EnumType::class,
                'options'  => $options + [
                    'class'    => $enumClass,
                    'required' => false,
                ] + $this->enumChoiceLabel($customOptions),
            ];
        }

        if (!empty($customOptions['renderAsSwitch'])) {
            return [
                'formType' => CheckboxType::class,
                'options'  => $options + ['required' => false],
            ];
        }

        if (!empty($customOptions['choices'])) {
            return [
                'formType' => ChoiceType::class,
                'options'  => $options + [
                    'choices'  => array_flip($customOptions['choices']),
                    'required' => false,
                ],
            ];
        }

        if (!empty($customOptions[MoneyColumn::OPTION_IS_MONEY])) {
            return [
                'formType' => MoneyType::class,
                'options'  => $options + $this->moneyOptions($customOptions),
            ];
        }

        if (!empty($customOptions['isEmail'])) {
            return [
                'formType' => EmailType::class,
                'options'  => $options,
            ];
        }

        $type = $column->getType();

        if (ColumnType::DATE === $type) {
            return [
                'formType' => DateType::class,
                'options'  => $options + ['widget' => 'single_text'],
            ];
        }

        if ($type->isNumber()) {
            return [
                'formType' => NumberType::class,
                'options'  => $options + ['html5' => true],
            ];
        }

        if (ColumnType::STRING === $type || ColumnType::STRING_UTF8 === $type) {
            return [
                'formType' => TextType::class,
                'options'  => $options,
            ];
        }

        if (ColumnType::HTML === $type) {
            return [
                'formType' => TextareaType::class,
                'options'  => $options,
            ];
        }

        return [
            'formType' => TextType::class,
            'options'  => $options,
        ];
    }

    /**
     * A column stored in cents is edited in currency units: 2500 opens as 25.00 and typing 25 saves
     * 2500.
     *
     * @param array<string, mixed> $customOptions
     *
     * @return array<string, mixed>
     */
    private function moneyOptions(array $customOptions): array
    {
        $decimals = (int) ($customOptions[MoneyColumn::OPTION_DECIMALS] ?? 2);
        $options  = [
            'currency' => $customOptions[MoneyColumn::OPTION_CURRENCY] ?? 'EUR',
            'scale'    => $decimals,
            'html5'    => true,
        ];

        if (false === ($customOptions[MoneyColumn::OPTION_STORED_AS_CENTS] ?? true)) {
            return $options;
        }

        return $options + [
            'divisor' => 10 ** $decimals,
            'input'   => 'integer',
        ];
    }

    /**
     * A property typed as an enum cannot be bound to a scalar form type: the data
     * would be cast to string and blow up. EnumType keeps the case objects intact.
     *
     * @return class-string<\UnitEnum>|null
     */
    private function resolveEnumClass(?object $entity, string $property): ?string
    {
        if (null === $entity || !property_exists($entity, $property)) {
            return null;
        }

        $type = (new \ReflectionProperty($entity, $property))->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        return enum_exists($name) ? $name : null;
    }

    /**
     * Reuse the labels declared on a ChoiceColumn instead of the raw case names.
     *
     * @param array<string, mixed> $customOptions
     *
     * @return array<string, mixed>
     */
    private function enumChoiceLabel(array $customOptions): array
    {
        $choices = $customOptions['choices'] ?? null;

        if (!\is_array($choices) || [] === $choices) {
            return [];
        }

        return [
            'choice_label' => static fn (\UnitEnum $case): string => $choices[(string) ($case instanceof \BackedEnum ? $case->value : $case->name)] ?? $case->name,
        ];
    }

    private function isSkippable(ColumnInterface $column, array $customOptions, ?object $entity): bool
    {
        if ($column instanceof ActionColumn) {
            return true;
        }

        if (isset($customOptions['hideWhenUpdating']) && true === $customOptions['hideWhenUpdating']) {
            return true;
        }

        if (isset($customOptions['templatePath'])) {
            return true;
        }

        if ($column instanceof UrlColumn) {
            return true;
        }

        if ($this->exceedsMoneyFormPrecision($customOptions)) {
            return true;
        }

        if (str_contains($column->getField() ?? '', '.')) {
            return true;
        }

        // A projected DTO names columns the entity may not carry. Adding those fields
        // makes PropertyAccess throw when the form is built against the source entity.
        return null !== $entity && !$this->isBindable($entity, $column->getName());
    }

    /**
     * @param array<string, mixed> $customOptions
     */
    private function exceedsMoneyFormPrecision(array $customOptions): bool
    {
        return !empty($customOptions[MoneyColumn::OPTION_IS_MONEY])
            && false !== ($customOptions[MoneyColumn::OPTION_STORED_AS_CENTS] ?? true)
            && (int) ($customOptions[MoneyColumn::OPTION_DECIMALS] ?? 2) > self::MAX_MONEY_FORM_DECIMALS;
    }

    private function isBindable(object $entity, string $property): bool
    {
        if ('' === $property) {
            return false;
        }

        return $this->propertyAccessor->isWritable($entity, $property)
            || $this->propertyAccessor->isReadable($entity, $property);
    }
}
