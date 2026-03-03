<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTableExtensions::class)]
final class DataTableExtensionsTest extends TestCase
{
    #[Test]
    public function it_constructs_with_valid_extensions(): void
    {
        $extensions = [
            'buttons' => [
                'copy',
                'csv',
                'excel',
                'pdf',
                'print',
            ],
            'select' => [
                'style' => 'single',
            ],
        ];

        $dataTableExtensions = new DataTableExtensions($extensions);

        $this->assertArrayHasKey('select', $dataTableExtensions->jsonSerialize());
    }
}
