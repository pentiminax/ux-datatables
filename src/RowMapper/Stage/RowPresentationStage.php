<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper\Stage;

use Pentiminax\UX\DataTables\Contracts\RowStageInterface;
use Pentiminax\UX\DataTables\RowMapper\RecordUrlFilter;
use Pentiminax\UX\DataTables\RowMapper\RowContext;

/**
 * Writes the DataTables-native `DT_RowClass` and `DT_RowAttr` keys from the table's row callables.
 */
final class RowPresentationStage implements RowStageInterface
{
    public const string RECORD_URL_ATTRIBUTE = 'data-record-url';

    public function __construct(
        private readonly ?\Closure $rowClass = null,
        private readonly ?\Closure $recordUrl = null,
    ) {
    }

    public function process(array $mappedRow, mixed $originalRow, array $columns): array
    {
        $source = $originalRow instanceof RowContext ? $originalRow->source : $originalRow;

        $classes = null === $this->rowClass ? '' : self::sanitizeClasses(($this->rowClass)($source));
        if ('' !== $classes) {
            $mappedRow['DT_RowClass'] = $classes;
        }

        $url = null === $this->recordUrl ? null : RecordUrlFilter::filter(($this->recordUrl)($source));
        if (null !== $url) {
            $existing                = \is_array($mappedRow['DT_RowAttr'] ?? null) ? $mappedRow['DT_RowAttr'] : [];
            $mappedRow['DT_RowAttr'] = [self::RECORD_URL_ATTRIBUTE => $url] + $existing;
        }

        return $mappedRow;
    }

    private static function sanitizeClasses(mixed $classes): string
    {
        if (!\is_string($classes)) {
            return '';
        }

        $tokens = preg_split('/\s+/', $classes, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $safe   = array_filter($tokens, static fn (string $token): bool => 1 === preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $token));

        return implode(' ', array_unique($safe));
    }
}
