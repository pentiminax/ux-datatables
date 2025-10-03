<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use PHPUnit\Framework\TestCase;

class DataTableExtensionsTest extends TestCase
{
    public function testDataTableExtensionsConstructor(): void
    {
        $extensions = [
            'buttons' => [
                'copy',
                'csv',
                'excel',
                'pdf',
                'print'
            ],
            'select' => [
                'style' => 'single'
            ]
        ];

        $dataTableExtensions = new DataTableExtensions($extensions);

        $expectedArray = [
            'select' => $extensions['select']
        ];

        $this->assertArrayHasKey('select', $dataTableExtensions->jsonSerialize());
    }
}