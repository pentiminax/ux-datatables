<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

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
    public function map(array $column): ?array
    {
        $customOptions = $column['customOptions'] ?? [];

        if ($this->isSkippable($column, $customOptions)) {
            return null;
        }

        $options = [
            'label' => $column['title'] ?? $column['name'] ?? null,
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

        if (isset($customOptions['dateFormat'])) {
            return [
                'formType' => DateType::class,
                'options'  => $options + ['widget' => 'single_text'],
            ];
        }

        $type = $column['type'] ?? null;

        if (\in_array($type, ['num', 'num-fmt', 'html-num', 'html-num-fmt'], true)) {
            return [
                'formType' => NumberType::class,
                'options'  => $options,
            ];
        }

        if (\in_array($type, ['string', 'string-utf8'], true)) {
            return [
                'formType' => TextType::class,
                'options'  => $options,
            ];
        }

        if ('html' === $type) {
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

    private function isSkippable(array $column, array $customOptions): bool
    {
        if (isset($customOptions['hideWhenUpdating']) && true === $customOptions['hideWhenUpdating']) {
            return true;
        }

        if (isset($column['actions'])) {
            return true;
        }

        if (isset($customOptions['templatePath'])) {
            return true;
        }

        if (isset($customOptions['routeName']) || isset($customOptions['template'])) {
            return true;
        }

        return false;
    }
}
