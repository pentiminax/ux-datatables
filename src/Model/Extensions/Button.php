<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;

final class Button implements \JsonSerializable
{
    private const DEFAULT_EXPORT_COLUMNS = ':visible:not(.not-exportable)';

    /**
     * Button types that never export data: no default `exportOptions` is injected for these.
     *
     * @var list<ButtonType>
     */
    private const NON_EXPORT_TYPES = [
        ButtonType::COLLECTION,
        ButtonType::COLUMN_CONTROL_SEARCH_CLEAR,
        ButtonType::COLUMN_VISIBILITY,
    ];

    /**
     * Utility button types serialized as a bare extend string when no other option is set. A
     * collection is never bare — its `buttons` array is the point of the button — so it is a
     * NON_EXPORT_TYPES member but not one of these.
     *
     * @var list<ButtonType>
     */
    private const BARE_STRING_TYPES = [
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

    /**
     * A dropdown grouping other buttons together, using DataTables' generic collection button
     * type — the same mechanism `Button::colVis()` builds on internally, made directly available
     * for a plain grouping dropdown (e.g. an "Export" menu holding several export buttons).
     *
     * @param list<Button|array<string, mixed>|string> $buttons
     */
    public static function collection(array $buttons): self
    {
        $button                     = self::fromType(ButtonType::COLLECTION);
        $button->options['buttons'] = $buttons;

        return $button;
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
        if (\in_array($this->type, self::BARE_STRING_TYPES, true) && [] === $this->options) {
            return $this->type->value;
        }

        $config = [
            'extend' => $this->type->value,
        ];

        if (!\in_array($this->type, self::NON_EXPORT_TYPES, true) && !\array_key_exists('exportOptions', $this->options)) {
            $config['exportOptions'] = [
                'columns' => self::DEFAULT_EXPORT_COLUMNS,
            ];
        }

        return array_merge($config, $this->options);
    }
}
