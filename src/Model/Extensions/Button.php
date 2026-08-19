<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;

final class Button implements \JsonSerializable
{
    private const DEFAULT_EXPORT_COLUMNS = ':visible:not(.not-exportable)';

    /**
     * Utility button types that never export data: no default `exportOptions`, and serialized as
     * a bare extend string when no other option is set.
     *
     * @var list<ButtonType>
     */
    private const NON_EXPORT_TYPES = [
        ButtonType::COLUMN_CONTROL_SEARCH_CLEAR,
        ButtonType::COLUMN_VISIBILITY,
    ];

    /** @var array<string, mixed> */
    private array $options = [];

    private function __construct(
        private readonly ButtonType $type,
    ) {
    }

    public static function fromType(ButtonType $type): self
    {
        return new self($type);
    }

    public static function copy(): self
    {
        return self::fromType(ButtonType::COPY);
    }

    public static function csv(): self
    {
        return self::fromType(ButtonType::CSV);
    }

    public static function excel(): self
    {
        return self::fromType(ButtonType::EXCEL);
    }

    public static function pdf(): self
    {
        return self::fromType(ButtonType::PDF);
    }

    public static function print(): self
    {
        return self::fromType(ButtonType::PRINT);
    }

    public static function colVis(): self
    {
        return self::fromType(ButtonType::COLUMN_VISIBILITY);
    }

    /**
     * Clears the global search and every ColumnControl per-column search, using the ColumnControl
     * extension's own native Buttons entry. Requires ColumnControlExtension on the table; enables
     * and disables itself automatically based on whether any search is currently active.
     */
    public static function ccSearchClear(): self
    {
        return self::fromType(ButtonType::COLUMN_CONTROL_SEARCH_CLEAR);
    }

    public function text(string $text): self
    {
        $this->options['text'] = $text;

        return $this;
    }

    public function className(string $className): self
    {
        $this->options['className'] = $className;

        return $this;
    }

    /**
     * @param array<string, mixed> $exportOptions
     */
    public function exportOptions(array $exportOptions): self
    {
        $this->options['exportOptions'] = $exportOptions;

        return $this;
    }

    public function option(string $name, mixed $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function jsonSerialize(): array|string
    {
        $isNonExport = \in_array($this->type, self::NON_EXPORT_TYPES, true);

        if ($isNonExport && [] === $this->options) {
            return $this->type->value;
        }

        $config = [
            'extend' => $this->type->value,
        ];

        if (!$isNonExport && !\array_key_exists('exportOptions', $this->options)) {
            $config['exportOptions'] = [
                'columns' => self::DEFAULT_EXPORT_COLUMNS,
            ];
        }

        return array_merge($config, $this->options);
    }
}
