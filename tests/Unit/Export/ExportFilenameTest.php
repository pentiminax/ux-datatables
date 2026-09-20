<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Export;

use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Export\ExportFilename;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExportFilename::class)]
final class ExportFilenameTest extends TestCase
{
    #[Test]
    #[DataProvider('filenames')]
    public function it_resolves_a_safe_filename(?string $filename, string $class, ExportFormat $format, string $expected): void
    {
        $this->assertSame($expected, ExportFilename::resolve($filename, $class, $format));
    }

    /**
     * @return iterable<string, array{?string, string, ExportFormat, string}>
     */
    public static function filenames(): iterable
    {
        yield 'explicit name' => ['users', 'App\\UserDataTable', ExportFormat::CSV, 'users.csv'];
        yield 'already has extension' => ['users.CSV', 'App\\UserDataTable', ExportFormat::CSV, 'users.CSV'];
        yield 'strips path' => ['../etc/passwd', 'App\\UserDataTable', ExportFormat::CSV, 'passwd.csv'];
        yield 'slug from class' => [null, 'App\\DataTable\\UserDataTable', ExportFormat::CSV, 'user-data-table.csv'];
        yield 'empty falls back' => ['', 'App\\UserDataTable', ExportFormat::CSV, 'user-data-table.csv'];
        yield 'xlsx extension' => ['users', 'App\\UserDataTable', ExportFormat::XLSX, 'users.xlsx'];
        yield 'xlsx keeps its extension' => ['users.xlsx', 'App\\UserDataTable', ExportFormat::XLSX, 'users.xlsx'];
        yield 'csv name exported as xlsx' => ['users.csv', 'App\\UserDataTable', ExportFormat::XLSX, 'users.csv.xlsx'];
        yield 'xlsx slug from class' => [null, 'App\\DataTable\\UserDataTable', ExportFormat::XLSX, 'user-data-table.xlsx'];
    }
}
