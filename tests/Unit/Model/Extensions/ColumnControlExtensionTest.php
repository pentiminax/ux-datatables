<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use PHPUnit\Framework\TestCase;

class ColumnControlExtensionTest  extends TestCase
{
    public function testColumnControlExtensionToArray(): void
    {
        $extension = new ColumnControlExtension();

        $expectedArray = [
            [
                'target' => 0,
                'content' => [
                    'order',
                    [
                        'orderAsc',
                        'orderDesc',
                        'spacer',
                        'orderAddAsc',
                        'orderAddDesc',
                        'spacer',
                        'orderRemove'
                    ]
                ],
            ],
            [
                'target' => 1,
                'content' => ['search'],
            ],
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }
}