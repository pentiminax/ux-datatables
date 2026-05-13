<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;

final class Button implements \JsonSerializable
{
    private const DEFAULT_EXPORT_COLUMNS = ':visible:not(.not-exportable)';

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
        if (ButtonType::COLUMN_VISIBILITY === $this->type && [] === $this->options) {
            return $this->type->value;
        }

        $config = [
            'extend' => $this->type->value,
        ];

        if (ButtonType::COLUMN_VISIBILITY !== $this->type && !\array_key_exists('exportOptions', $this->options)) {
            $config['exportOptions'] = [
                'columns' => self::DEFAULT_EXPORT_COLUMNS,
            ];
        }

        return array_merge($config, $this->options);
    }
}
