<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Enum\ExportFormat;

interface ExporterInterface
{
    public function format(): ExportFormat;

    /**
     * Whether the underlying writer library is installed. Checked before any header is sent so a
     * missing optional dependency surfaces as a clean error response rather than a broken download.
     */
    public function isAvailable(): bool;

    /**
     * Writes the whole export to php://output.
     *
     * @param list<ColumnInterface>          $columns
     * @param iterable<array<string, mixed>> $rows
     */
    public function write(array $columns, iterable $rows): void;
}
