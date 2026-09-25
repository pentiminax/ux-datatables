<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Highlight;

/**
 * Client-side configuration for the update highlight: after a real-time refresh, the cells whose
 * value changed are briefly emphasized so the viewer can tell what moved.
 */
final class HighlightConfig implements \JsonSerializable
{
    /**
     * Row data key holding the identifier the client diffs rows on. Also fed to the DataTables
     * `rowId` option, so each `<tr>` carries it as its DOM id.
     */
    public const string ROW_ID_KEY = 'DT_RowId';

    /**
     * Property the row identifier is read from unless the table configures another one. Emitted to
     * the client only when it differs, since API Platform rows carry no server-side row id and the
     * frontend adapter has to add one itself.
     */
    public const string DEFAULT_ID_FIELD = 'id';

    /**
     * @var string[]
     */
    public readonly array $ignoreColumns;

    /**
     * @param string[] $ignoreColumns Data keys of columns whose value changes on every refresh
     *                                (relative dates, counters); highlighting them would bury the
     *                                real updates under constant noise
     */
    public function __construct(
        public readonly int $durationMs = 1200,
        array $ignoreColumns = [],
        public readonly string $idField = self::DEFAULT_ID_FIELD,
    ) {
        if ($durationMs <= 0) {
            throw new \InvalidArgumentException('Highlight duration must be a positive number of milliseconds.');
        }

        if ('' === $idField) {
            throw new \InvalidArgumentException('Highlight id field cannot be empty.');
        }

        $this->ignoreColumns = array_values(array_filter(
            $ignoreColumns,
            static fn (mixed $value): bool => \is_string($value) && '' !== $value,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = ['durationMs' => $this->durationMs];

        if ([] !== $this->ignoreColumns) {
            $data['ignoreColumns'] = $this->ignoreColumns;
        }

        if (self::DEFAULT_ID_FIELD !== $this->idField) {
            $data['idField'] = $this->idField;
        }

        return $data;
    }
}
