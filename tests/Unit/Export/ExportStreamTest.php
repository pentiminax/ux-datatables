<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Export;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Export\ExportStream;
use Pentiminax\UX\DataTables\Tests\Support\RecordingExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExportStream::class)]
final class ExportStreamTest extends TestCase
{
    #[Test]
    public function it_forwards_columns_and_rows_to_the_exporter(): void
    {
        $exporter = new RecordingExporter();
        $columns  = [TextColumn::new('email')];

        (new ExportStream($exporter, $columns, [['email' => 'ada@example.com']]))();

        $this->assertSame($columns, $exporter->columns);
        $this->assertSame([['email' => 'ada@example.com']], $exporter->rows);
    }

    #[Test]
    public function it_lets_a_mid_stream_failure_reach_the_application_error_handling(): void
    {
        $stream = new ExportStream(
            new RecordingExporter(failure: new \RuntimeException('Row 12 blew up.')),
            [TextColumn::new('email')],
            [],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Row 12 blew up.');

        $stream();
    }
}
