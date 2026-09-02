<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AbstractWriterMultiSheets;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Pentiminax\UX\DataTables\Enum\ExportFormat;

/**
 * Unlike the CSV writer, OpenSpout's XLSX writer builds the workbook in a temporary folder and only
 * copies the resulting archive into the output pointer on close()
 * ({@see \OpenSpout\Writer\XLSX\Helper\FileSystemHelper::zipRootFolderAndCopyToStream()}). Memory
 * stays bounded, but the export is not streamed: nothing reaches the client until the last row is
 * written, and the workbook needs temporary disk space. `ext-zip` comes with openspout/openspout.
 */
final class XlsxExporter extends AbstractExporter
{
    private const COLUMN_WIDTH = 24;

    public function format(): ExportFormat
    {
        return ExportFormat::XLSX;
    }

    public function isAvailable(): bool
    {
        return class_exists(Writer::class);
    }

    protected function createWriter(): WriterInterface
    {
        return new Writer();
    }

    protected function createHeader(array $columns): Row
    {
        return Row::fromValues($this->headings($columns), (new Style())->setFontBold());
    }

    protected function configureSheet(WriterInterface $writer, array $columns): void
    {
        if (!$writer instanceof AbstractWriterMultiSheets) {
            return;
        }

        $columnCount = \count($columns);
        $sheet       = $writer->getCurrentSheet();

        $sheet->setAutoFilter(new AutoFilter(0, 1, max($columnCount - 1, 0), $writer->getWrittenRowCount()));
        $sheet->setSheetView((new SheetView())->setFreezeRow(2));
        $sheet->setColumnWidthForRange(self::COLUMN_WIDTH, 1, max($columnCount, 1));
    }
}
