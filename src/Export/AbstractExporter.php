<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\WriterInterface;
use Pentiminax\UX\DataTables\Column\Rendering\ColumnKeyResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ExportFormat;

/**
 * Shared spreadsheet export pipeline: headings, per-row cell conversion, and the write loop. A
 * concrete exporter only supplies its writer and, optionally, post-write sheet configuration.
 *
 * Writers always open `php://output`, never `openToBrowser()`: the latter calls `header()` itself,
 * which collides with the headers a {@see \Symfony\Component\HttpFoundation\StreamedResponse} has
 * already sent.
 */
abstract class AbstractExporter implements ExporterInterface
{
    /**
     * Leading characters Excel and LibreOffice treat as the start of a formula.
     */
    private const FORMULA_PREFIXES = "=@\t\r";

    /**
     * Formula prefixes that are also valid number prefixes, so they are only neutralized when the
     * value is not a number.
     */
    private const SIGN_PREFIXES = '+-';

    abstract public function format(): ExportFormat;

    final public function write(array $columns, iterable $rows): void
    {
        $writer = $this->createWriter();
        $writer->openToFile('php://output');
        $writer->addRow($this->createHeader($columns));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($this->processRow($columns, $row)));

            flush();
        }

        $this->configureSheet($writer, $columns);
        $writer->close();
    }

    abstract protected function createWriter(): WriterInterface;

    /**
     * @param list<ColumnInterface> $columns
     */
    protected function createHeader(array $columns): Row
    {
        return Row::fromValues($this->headings($columns));
    }

    /**
     * @param list<ColumnInterface> $columns
     *
     * @return list<string>
     */
    protected function headings(array $columns): array
    {
        $headings = [];
        foreach ($columns as $column) {
            $headings[] = $column->getTitle() ?? $column->getName();
        }

        return $headings;
    }

    /**
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $row
     *
     * @return list<float|int|string>
     */
    protected function processRow(array $columns, array $row): array
    {
        $cells = [];
        foreach ($columns as $column) {
            $cells[] = $this->cellValue($column, $row);
        }

        return $cells;
    }

    /**
     * Runs once after the last row, while the sheet is still open.
     *
     * @param list<ColumnInterface> $columns
     */
    protected function configureSheet(WriterInterface $writer, array $columns): void
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function cellValue(ColumnInterface $column, array $row): float|int|string
    {
        $key = ColumnKeyResolver::rowKey($column);
        if (null === $key) {
            return '';
        }

        $value = $row[$key] ?? '';

        if (\is_int($value) || \is_float($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_array($value) || $value instanceof \JsonSerializable) {
            return $this->neutralizeFormula((string) json_encode($value, \JSON_UNESCAPED_UNICODE));
        }

        if (\is_object($value) && !method_exists($value, '__toString')) {
            return '';
        }

        return $this->neutralizeFormula(
            trim(html_entity_decode(strip_tags((string) $value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')),
        );
    }

    /**
     * Prefixes a leading formula character with an apostrophe so spreadsheet software renders the
     * cell as text instead of evaluating it. `+` and `-` are left alone on numeric values, so a
     * Doctrine decimal (returned as the string `-42.50`) stays a number rather than becoming text.
     */
    private function neutralizeFormula(string $value): string
    {
        if ('' === $value) {
            return $value;
        }

        $first = $value[0];

        if (str_contains(self::FORMULA_PREFIXES, $first)) {
            return "'".$value;
        }

        return str_contains(self::SIGN_PREFIXES, $first) && !is_numeric($value) ? "'".$value : $value;
    }
}
