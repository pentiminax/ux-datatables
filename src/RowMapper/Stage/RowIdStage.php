<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;
use Pentiminax\UX\DataTables\Highlight\HighlightConfig;
use Pentiminax\UX\DataTables\RowMapper\RowContext;

/**
 * Exposes the row identifier under {@see HighlightConfig::ROW_ID_KEY}.
 *
 * Rows otherwise carry only column keys, which leaves the client no way to tell a row that changed
 * from a row that merely moved when the refreshed page comes back in a different order.
 */
final class RowIdStage implements RowStageInterface
{
    public function __construct(
        private readonly string $idField = 'id',
    ) {
    }

    public function process(array $mappedRow, mixed $originalRow, array $columns): array
    {
        if (\array_key_exists(HighlightConfig::ROW_ID_KEY, $mappedRow)) {
            return $mappedRow;
        }

        $source = $originalRow instanceof RowContext ? $originalRow->source : $originalRow;
        $id     = $this->normalizeId(PropertyReader::readPath($source, $this->idField));

        if (null === $id) {
            return $mappedRow;
        }

        $mappedRow[HighlightConfig::ROW_ID_KEY] = $id;

        return $mappedRow;
    }

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
}
