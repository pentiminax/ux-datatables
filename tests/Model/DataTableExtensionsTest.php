<?php

namespace Pentiminax\UX\DataTables\Tests\Model;

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
            'layout' => [
                'topStart' => [
                    'buttons' => $extensions['buttons']
                ]
            ],
            'select' => $extensions['select']
        ];

        $this->assertEquals($expectedArray, $dataTableExtensions->toArray());
    }
}