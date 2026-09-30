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
     * @return array<string, string>
     */
    private function extractExistingMetadata(array $mappedRow): array
    {
        $metadata = $mappedRow[self::METADATA_KEY] ?? [];

        if (!\is_array($metadata)) {
            return [];
        }

        $normalized = [];

        foreach ($metadata as $field => $value) {
            if (!\is_string($field) || '' === $field) {
                continue;
            }

            $id = $this->normalizeId($value);

            if (null !== $id) {
                $normalized[$field] = $id;
            }
        }

        return $normalized;
    }

    private function resolveSwitchId(mixed $source, BooleanColumn $column): ?string
    {
        $idField = $column->getCustomOption(BooleanColumn::OPTION_TOGGLE_ID_FIELD);
        if (!\is_string($idField) || '' === $idField) {
            $idField = 'id';
        }

        return $this->normalizeId(PropertyReader::readPath($source, $idField));
    }

    /**
     * Identifiers travel through JSON into browser attributes. Keeping integers as numbers
     * loses precision above Number.MAX_SAFE_INTEGER, so every usable id becomes a string —
     * matching {@see RowIdStage}.
     */
    private function normalizeId(mixed $id): ?string
    {
        if (\is_int($id)) {
            return (string) $id;
        }

        if (\is_string($id) || $id instanceof \Stringable) {
            $id = (string) $id;

            return '' !== $id ? $id : null;
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
