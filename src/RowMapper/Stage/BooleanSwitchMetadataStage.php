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

            if (\is_int($value)) {
                $normalized[$field] = (string) $value;

                continue;
            }

            if (\is_string($value) && '' !== $value) {
                $normalized[$field] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Identifiers are strings so JSON does not round them through IEEE-754 the way a JSON
     * number would above Number.MAX_SAFE_INTEGER.
     */
    private function resolveSwitchId(mixed $source, BooleanColumn $column): ?string
    {
        $idField = $column->getCustomOption(BooleanColumn::OPTION_TOGGLE_ID_FIELD);
        if (!\is_string($idField) || '' === $idField) {
            $idField = 'id';
        }

        $id = PropertyReader::readPath($source, $idField);

        if (\is_int($id)) {
            return (string) $id;
        }

        if (\is_string($id) && '' !== $id) {
            return $id;
        }

        if ($id instanceof \Stringable) {
            $stringId = (string) $id;

            return '' !== $stringId ? $stringId : null;
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
