<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Export;

use OpenSpout\Writer\XLSX\Writer;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Export\XlsxExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(XlsxExporter::class)]
final class XlsxExporterTest extends TestCase
{
    #[Test]
    public function it_reports_its_format(): void
    {
        $this->assertSame(ExportFormat::XLSX, (new XlsxExporter())->format());
    }

    #[Test]
    public function it_writes_a_zip_workbook_holding_the_rows(): void
    {
        if (!class_exists(Writer::class) || !\extension_loaded('zip')) {
            $this->markTestSkipped('openspout/openspout and ext-zip are required for this test.');
        }

        $path = tempnam(sys_get_temp_dir(), 'ux-datatables-xlsx-');
        self::assertIsString($path);

        try {
            $this->writeTo($path);

            $this->assertStringStartsWith("PK\x03\x04", (string) file_get_contents($path));

            $text = $this->workbookText($path);

            $this->assertStringContainsString('Email', $text);
            $this->assertStringContainsString('Bio', $text);
            $this->assertStringContainsString('ada@example.com', $text);
            $this->assertStringContainsString("'=cmd|'/c calc'!A1", $text);
        } finally {
            @unlink($path);
        }
    }

    private function writeTo(string $path): void
    {
        $handle = fopen($path, 'wb');
        self::assertIsResource($handle);

        ob_start(static function (string $buffer) use ($handle): string {
            fwrite($handle, $buffer);

            return '';
        }, 8192);

        try {
            (new XlsxExporter())->write(
                [TextColumn::new('email', 'Email'), TextColumn::new('bio', 'Bio')],
                [['email' => 'ada@example.com', 'bio' => "=cmd|'/c calc'!A1"]],
            );
        } finally {
            ob_end_flush();
            fclose($handle);
        }
    }

    /**
     * Cell values live either in the sheet (inline strings, the OpenSpout default) or in the shared
     * strings table, so both are concatenated and unescaped before being asserted on.
     */
    private function workbookText(string $path): string
    {
        $zip = new \ZipArchive();
        self::assertTrue(true === $zip->open($path));

        $xml = '';
        foreach (['xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml'] as $entry) {
            $contents = $zip->getFromName($entry);
            if (\is_string($contents)) {
                $xml .= $contents;
            }
        }

        $zip->close();

        self::assertNotSame('', $xml);

        return html_entity_decode($xml, \ENT_QUOTES | \ENT_XML1, 'UTF-8');
    }
}
