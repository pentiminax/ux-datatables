<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;
use Pentiminax\UX\DataTables\RowMapper\RowContext;

final class BooleanSwitchMetadataStage implements RowStageInterface
{
    public const string METADATA_KEY = '__ux_datatables_boolean_switches';

    public function process(array $mappedRow, mixed $originalRow, array $columns): array
    {
        $metadata = $this->extractExistingMetadata($mappedRow);
        $source   = $originalRow instanceof RowContext ? $originalRow->source : $originalRow;

        foreach ($columns as $column) {
            if (!$column instanceof BooleanColumn || !$column->isRenderedAsSwitch()) {
                continue;
            }

            $id = $this->resolveSwitchId($source, $column);
            if (null === $id) {
                continue;
            }

            $effectiveField = $this->resolveEffectiveField($column);
            if ('' === $effectiveField) {
                continue;
            }

            $metadata[$effectiveField] = $id;
        }

        if ([] === $metadata) {
            unset($mappedRow[self::METADATA_KEY]);

            return $mappedRow;
        }

        $mappedRow[self::METADATA_KEY] = $metadata;

        return $mappedRow;
    }

    /**
     * @return array<string, int|string>
     */
    private function extractExistingMetadata(array $mappedRow): array
    {
        $metadata = $mappedRow[self::METADATA_KEY] ?? [];

        return \is_array($metadata) ? array_filter(
            $metadata,
            static fn (mixed $value, mixed $field): bool => \is_string($field)
                && '' !== $field
                && (\is_int($value) || \is_string($value)),
            \ARRAY_FILTER_USE_BOTH,
        ) : [];
    }

    private function resolveSwitchId(mixed $source, BooleanColumn $column): int|string|null
    {
        $idField = $column->getCustomOption(BooleanColumn::OPTION_TOGGLE_ID_FIELD);
        if (!\is_string($idField) || '' === $idField) {
            $idField = 'id';
        }

        $id = PropertyReader::readPath($source, $idField);

        if (\is_int($id) || \is_string($id)) {
            return $id;
        }

        if ($id instanceof \Stringable) {
            return (string) $id;
        }

        return null;
    }

    private function resolveEffectiveField(ColumnInterface $column): string
    {
        $toggleField = $column->getCustomOption(BooleanColumn::OPTION_TOGGLE_FIELD);
        if (\is_string($toggleField) && '' !== $toggleField) {
            return $toggleField;
        }

        foreach ([$column->getField(), $column->getData(), $column->getName()] as $field) {
            if (\is_string($field) && '' !== $field) {
                return $field;
            }
        }

        return '';
    }
}
