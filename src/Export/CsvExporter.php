<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Export;

use OpenSpout\Writer\CSV\Options;
use OpenSpout\Writer\CSV\Writer;
use OpenSpout\Writer\WriterInterface;
use Pentiminax\UX\DataTables\Enum\ExportFormat;

final class CsvExporter extends AbstractExporter
{
    public function format(): ExportFormat
    {
        return ExportFormat::CSV;
    }

    public function isAvailable(): bool
    {
        return class_exists(Writer::class);
    }

    protected function createWriter(): WriterInterface
    {
        $options                  = new Options();
        $options->FIELD_DELIMITER = ',';
        $options->SHOULD_ADD_BOM  = true;

        return new Writer($options);
    }
}
