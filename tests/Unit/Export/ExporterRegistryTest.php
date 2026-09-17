<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Export;

use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Export\ExporterRegistry;
use Pentiminax\UX\DataTables\Tests\Support\RecordingExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @internal
 */
#[CoversClass(ExporterRegistry::class)]
final class ExporterRegistryTest extends TestCase
{
    #[Test]
    public function it_indexes_exporters_by_their_own_format(): void
    {
        $csv      = new RecordingExporter();
        $xlsx     = new RecordingExporter(ExportFormat::XLSX);
        $registry = new ExporterRegistry([$csv, $xlsx]);

        $this->assertSame($csv, $registry->get(ExportFormat::CSV));
        $this->assertSame($xlsx, $registry->get(ExportFormat::XLSX));
    }

    #[Test]
    public function it_rejects_an_unregistered_format(): void
    {
        $registry = new ExporterRegistry([new RecordingExporter()]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('No exporter is registered for the "xlsx" format.');

        $registry->get(ExportFormat::XLSX);
    }

    #[Test]
    public function it_rejects_an_exporter_whose_library_is_missing(): void
    {
        $registry = new ExporterRegistry([new RecordingExporter(ExportFormat::XLSX, available: false)]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Server-side XLSX export requires openspout/openspout.');

        $registry->get(ExportFormat::XLSX);
    }
}
