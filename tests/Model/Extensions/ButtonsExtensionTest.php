<?php

namespace Pentiminax\UX\DataTables\Tests\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use PHPUnit\Framework\TestCase;

class ButtonsExtensionTest extends TestCase
{
    public function testButtonsExtensionToArray(): void
    {
        $buttons = [];
        foreach (ButtonType::cases() as $buttonType) {
            $buttons[] = $buttonType->value;
        }

        $extension = new ButtonsExtension($buttons);

        $expectedArray = [
            [
                'extend' => ButtonType::COPY->value,
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend' => ButtonType::CSV->value,
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend' => ButtonType::EXCEL->value,
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend' => ButtonType::PDF->value,
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend' => ButtonType::PRINT->value,
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }
}