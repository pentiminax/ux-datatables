<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Export;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ColumnKeyResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Writes what the table displays: the browser formats Money, Choice and Boolean cells client-side,
 * so an export of the mapped row would otherwise carry the raw value (cents, backing value, 1/0).
 *
 * A column with formatValueUsing() is already formatted by the row pipeline and is left alone.
 */
final class ExportValueFormatter
{
    public function __construct(private readonly ?TranslatorInterface $translator = null)
    {
    }

    /**
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $row
     *
     * @return array<string, mixed>
     */
    public function formatRow(array $columns, array $row): array
    {
        foreach ($columns as $column) {
            $key = ColumnKeyResolver::rowKey($column);
            if (null === $key || !\array_key_exists($key, $row) || $this->isFormatted($column)) {
                continue;
            }

            $row[$key] = $this->formatValue($column, $row[$key]);
        }

        return $row;
    }

    /**
     * The currency belongs to the heading, so the cell stays a number a spreadsheet can sum.
     */
    public function heading(ColumnInterface $column, string $heading): string
    {
        if (!$column instanceof MoneyColumn || $this->isFormatted($column)) {
            return $heading;
        }

        return \sprintf('%s (%s)', $heading, $column->getCustomOption(MoneyColumn::OPTION_CURRENCY));
    }

    private function formatValue(ColumnInterface $column, mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        return match (true) {
            $column instanceof MoneyColumn   => $this->formatMoney($column, $value),
            $column instanceof ChoiceColumn  => $this->formatChoice($column, $value),
            $column instanceof BooleanColumn => $this->formatBoolean($value),
            default                          => $value,
        };
    }

    private function formatMoney(MoneyColumn $column, mixed $value): mixed
    {
        if (false === $column->getCustomOption(MoneyColumn::OPTION_STORED_AS_CENTS) || !is_numeric($value)) {
            return $value;
        }

        $decimals = $column->getCustomOption(MoneyColumn::OPTION_DECIMALS);

        return round($value / 10 ** $decimals, $decimals);
    }

    private function formatChoice(ChoiceColumn $column, mixed $value): mixed
    {
        $choices = $column->getCustomOption(ChoiceColumn::OPTION_CHOICES);

        return \is_scalar($value) && \is_array($choices) ? ($choices[(string) $value] ?? $value) : $value;
    }

    private function formatBoolean(mixed $value): mixed
    {
        $state = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if (null === $state) {
            return $value;
        }

        $key = $state ? 'export.boolean.true' : 'export.boolean.false';

        return $this->translator?->trans($key, domain: FilterLabels::DOMAIN) ?? ($state ? 'Yes' : 'No');
    }

    private function isFormatted(ColumnInterface $column): bool
    {
        return $column instanceof AbstractColumn && null !== $column->getValueFormatter();
    }
}
