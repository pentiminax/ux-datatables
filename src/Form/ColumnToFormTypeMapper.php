<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class ColumnToFormTypeMapper
{
    /**
     * @return array{formType: class-string, options: array<string, mixed>}|null
     */
    public function map(ColumnInterface $column): ?array
    {
        $customOptions = $column->getCustomOptions();

        if ($this->isSkippable($column, $customOptions)) {
            return null;
        }

        $options = [
            'label' => $column->getTitle() ?: $column->getName(),
        ];

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

        if (!empty($customOptions['isEmail'])) {
            return [
                'formType' => EmailType::class,
                'options'  => $options,
            ];
        }

        if (null !== $column->getCustomOption('dateFormat')) {
            return [
                'formType' => DateType::class,
                'options'  => $options + ['widget' => 'single_text'],
            ];
        }

        $type = $column->getType();

        if ($type->isNumber()) {
            return [
                'formType' => NumberType::class,
                'options'  => $options,
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

    private function isSkippable(ColumnInterface $column, array $customOptions): bool
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

        if (isset($customOptions['routeName']) || isset($customOptions['template'])) {
            return true;
        }

        if (str_contains($column->getField() ?? '', '.')) {
            return true;
        }

        return false;
    }
}
