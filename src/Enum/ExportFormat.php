<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum ExportFormat: string
{
    case CSV  = 'csv';
    case XLSX = 'xlsx';

    public static function fromButtonType(ButtonType $type): ?self
    {
        return match ($type) {
            ButtonType::CSV   => self::CSV,
            ButtonType::EXCEL => self::XLSX,
            default           => null,
        };
    }

    public function extension(): string
    {
        return $this->value;
    }

    public function contentType(): string
    {
        return match ($this) {
            self::CSV  => 'text/csv; charset=UTF-8',
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
