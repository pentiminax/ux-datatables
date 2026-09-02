<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Enum\ExportFormat;

final class Button implements \JsonSerializable
{
    public const string SERVER_EXPORT_ACTION = 'ux:export';

    private const string DEFAULT_EXPORT_COLUMNS = ':visible:not(.not-exportable)';

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

    private ?ExportFormat $exportFormat = null;

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
        return self::fromType(ButtonType::COPY)
            ->text('Copy');
    }

    /**
     * @param bool $serverSide stream every filtered row from PHP instead of exporting the rows
     *                         DataTables currently holds client-side
     */
    public static function csv(bool $serverSide = false): self
    {
        return self::exportButton(ButtonType::CSV, $serverSide)
            ->text('CSV');
    }

    /**
     * @param bool $serverSide stream every filtered row from PHP as an XLSX workbook instead of
     *                         exporting the rows DataTables currently holds client-side
     */
    public static function excel(bool $serverSide = false): self
    {
        return self::exportButton(ButtonType::EXCEL, $serverSide)
            ->text('Excel');
    }

    public static function pdf(): self
    {
        return self::fromType(ButtonType::PDF)
            ->text('PDF');
    }

    public static function print(): self
    {
        return self::fromType(ButtonType::PRINT)
            ->text('Print');
    }

    public static function colVis(): self
    {
        return self::fromType(ButtonType::COLUMN_VISIBILITY)
            ->text('Column Visibility');
    }

    /**
     * Clears the global search and every ColumnControl per-column search, using the ColumnControl
     * extension's own native Buttons entry. Requires ColumnControlExtension on the table; enables
     * and disables itself automatically based on whether any search is currently active.
     */
    public static function ccSearchClear(): self
    {
        return self::fromType(ButtonType::COLUMN_CONTROL_SEARCH_CLEAR)
            ->text('Clear Search');
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

    /**
     * A button whose click behavior is defined in JavaScript rather than PHP.
     *
     * DataTables button `action` callbacks are JavaScript functions and cannot be serialized from
     * PHP. $action is a name the frontend resolves to a real callback registered through the
     * `buttonActions` registry (`import { buttonActions } from '@pentiminax/ux-datatables'`), the
     * same way row actions resolve behavior by name rather than by shipping a closure.
     */
    public static function custom(string $action): self
    {
        if ('' === trim($action)) {
            throw new \InvalidArgumentException('Custom button action must not be empty.');
        }

        $button                    = self::fromType(ButtonType::CUSTOM);
        $button->options['action'] = $action;

        return $button;
    }

    private static function exportButton(ButtonType $type, bool $serverSide): self
    {
        $button = self::fromType($type);

        $format = $serverSide ? ExportFormat::fromButtonType($type) : null;
        if (null === $format) {
            return $button;
        }

        $button->exportFormat         = $format;
        $button->options['action']    = self::SERVER_EXPORT_ACTION;
        $button->options['format']    = $format->value;
        $button->options['exportKey'] = $format->value;

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

    public function filename(string $filename): self
    {
        $this->options['filename'] = $filename;

        return $this;
    }

    public function getFilename(): ?string
    {
        $filename = $this->options['filename'] ?? null;

        return \is_string($filename) && '' !== $filename ? $filename : null;
    }

    public function exportKey(string $exportKey): self
    {
        $exportKey = trim($exportKey);
        if ('' === $exportKey) {
            throw new \InvalidArgumentException('Export key must not be empty.');
        }

        $this->options['exportKey'] = $exportKey;

        return $this;
    }

    public function getExportKey(): string
    {
        $default = ($this->exportFormat ?? ExportFormat::CSV)->value;
        $key     = $this->options['exportKey'] ?? $default;

        return \is_string($key) && '' !== $key ? $key : $default;
    }

    public function isServerSideExport(): bool
    {
        return null !== $this->exportFormat;
    }

    public function getExportFormat(): ?ExportFormat
    {
        return $this->exportFormat;
    }

    /**
     * Nested button descriptors (collection / colvis prefix/postfix). Used to discover
     * server-side export buttons that live inside a dropdown.
     *
     * @return list<Button|array<string, mixed>|string>
     */
    public function getChildButtons(): array
    {
        $nested = [];
        foreach (['buttons', 'prefixButtons', 'postfixButtons'] as $key) {
            $value = $this->options[$key] ?? null;
            if (\is_array($value)) {
                $nested = [...$nested, ...$value];
            }
        }

        return $nested;
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

        if (null !== $this->exportFormat) {
            $payload = $this->options;
            unset($payload['extend'], $payload['exportOptions']);
            $payload['action']    = self::SERVER_EXPORT_ACTION;
            $payload['format']    = $this->exportFormat->value;
            $payload['exportKey'] = $this->getExportKey();

            return $payload;
        }

        if (ButtonType::CUSTOM === $this->type) {
            $action = $this->options['action'] ?? null;

            if (!\is_string($action) || '' === $action) {
                throw new \LogicException('A custom button must have an "action" name set. Use Button::custom().');
            }

            return $this->options;
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
