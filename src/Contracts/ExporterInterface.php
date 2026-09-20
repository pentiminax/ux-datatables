<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Enum\ExportFormat;

/**
 * Writes a server-side export of every filtered row to the response body.
 *
 * write() is called from inside a StreamedResponse, after the headers have gone out: it must
 * consume $rows once as a stream rather than buffering them, and it must not throw for a
 * recoverable reason -- an error can no longer become an error page. Everything checkable belongs
 * in isAvailable(), which ExporterRegistry consults before any header is sent.
 *
 * format() keys the exporter in {@see \Pentiminax\UX\DataTables\Export\ExporterRegistry}, and
 * ExportFormat is a closed enum: an implementation replaces the CSV or XLSX writer rather than
 * adding a format. Extend {@see \Pentiminax\UX\DataTables\Export\AbstractExporter} to keep the
 * shared heading, cell-conversion, and formula-neutralization behavior.
 */
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
