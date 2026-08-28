<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Export\ExporterInterface;

final class RecordingExporter implements ExporterInterface
{
    /** @var list<ColumnInterface> */
    public array $columns = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public function __construct(
        private readonly ExportFormat $exportFormat = ExportFormat::CSV,
        private readonly bool $available = true,
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function format(): ExportFormat
    {
        return $this->exportFormat;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function write(array $columns, iterable $rows): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $this->columns = $columns;
        $this->rows    = iterator_to_array($rows, false);
    }
}
