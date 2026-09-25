<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Export;

use OpenSpout\Writer\CSV\Writer;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Export\AbstractExporter;
use Pentiminax\UX\DataTables\Export\CsvExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CsvExporter::class)]
#[CoversClass(AbstractExporter::class)]
final class CsvExporterTest extends TestCase
{
    #[Test]
    public function it_reports_its_format(): void
    {
        $this->assertSame(ExportFormat::CSV, (new CsvExporter())->format());
    }

    #[Test]
    public function it_writes_a_utf8_bom_csv_skipping_html(): void
    {
        $csv = $this->export(
            [TextColumn::new('email', 'Email'), TextColumn::new('bio', 'Bio')],
            [['email' => 'ada@example.com', 'bio' => '<b>Hello</b>']],
        );

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Email,Bio', $csv);
        $this->assertStringContainsString('ada@example.com,Hello', $csv);
        $this->assertStringNotContainsString('<b>', $csv);
    }

    #[Test]
    public function it_falls_back_to_the_column_name_when_no_title_is_set(): void
    {
        $this->assertStringContainsString('email', $this->export([TextColumn::new('email')], []));
    }

    #[Test]
    public function it_writes_an_empty_cell_for_a_missing_row_key(): void
    {
        $csv = $this->export(
            [TextColumn::new('email', 'Email'), TextColumn::new('missing', 'Missing')],
            [['email' => 'ada@example.com']],
        );

        $this->assertStringContainsString('ada@example.com,', $csv);
    }

    #[Test]
    #[DataProvider('formulaCandidates')]
    public function it_neutralizes_spreadsheet_formulas(string $value, string $expected): void
    {
        $csv = $this->export([TextColumn::new('value', 'Value')], [['value' => $value]]);

        $this->assertStringContainsString($expected, $csv);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formulaCandidates(): iterable
    {
        yield 'equals' => ['=1+1', "'=1+1"];
        yield 'command injection' => ["=cmd|'/c calc'!A1", "'=cmd"];
        yield 'at sign' => ['@SUM(A1:A2)', "'@SUM(A1:A2)"];
        yield 'non numeric plus' => ['+A1', "'+A1"];
        yield 'non numeric minus' => ['-2+3+cmd', "'-2+3+cmd"];
        yield 'negative decimal stays a number' => ['-42.50', '-42.50'];
        yield 'negative integer stays a number' => ['-42', '-42'];
        yield 'plain text untouched' => ['Ada', 'Ada'];
    }

    #[Test]
    public function it_converts_scalar_and_object_values(): void
    {
        $csv = $this->export(
            [
                TextColumn::new('date', 'Date'),
                TextColumn::new('active', 'Active'),
                TextColumn::new('tags', 'Tags'),
                TextColumn::new('opaque', 'Opaque'),
                TextColumn::new('count', 'Count'),
            ],
            [[
                'date'   => new \DateTimeImmutable('2026-03-04 05:06:07'),
                'active' => true,
                'tags'   => ['a', 'b'],
                'opaque' => new \stdClass(),
                'count'  => 42,
            ]],
        );

        $this->assertStringContainsString('2026-03-04 05:06:07', $csv);
        $this->assertStringContainsString('"[""a"",""b""]"', $csv);
        $this->assertStringContainsString(',42', $csv);
    }

    #[Test]
    public function it_collapses_whitespace_left_by_markup(): void
    {
        $output = $this->export(
            [TextColumn::new('user', 'User')],
            [['user' => "<span>HS</span>\n\n        Henry Smith\n"]],
        );

        $this->assertStringContainsString('HS Henry Smith', $output);
        $this->assertSame(2, substr_count($output, "\n"), 'Header and a single row, no cell spilling over lines');
    }

    /**
     * @param list<\Pentiminax\UX\DataTables\Contracts\ColumnInterface> $columns
     * @param list<array<string, mixed>>                                $rows
     */
    private function export(array $columns, array $rows): string
    {
        if (!class_exists(Writer::class)) {
            $this->markTestSkipped('openspout/openspout is required for this test.');
        }

        // The exporter flushes its own output buffer as it streams, so an outer buffer collects
        // what the inner one hands off before the remainder is pushed into it.
        ob_start();
        ob_start();

        try {
            (new CsvExporter())->write($columns, $rows);
        } finally {
            ob_end_flush();
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
