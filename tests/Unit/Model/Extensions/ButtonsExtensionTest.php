<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ButtonsExtension::class)]
#[CoversClass(Button::class)]
final class ButtonsExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_to_array(): void
    {
        $buttons = [];
        foreach (ButtonType::cases() as $buttonType) {
            $buttons[] = $buttonType->value;
        }

        $extension = new ButtonsExtension($buttons);

        $expectedArray = [
            'colvis',
            [
                'extend'        => 'copy',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend'        => 'csv',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend'        => 'excel',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend'        => 'pdf',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend'        => 'print',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_mixed_button_types_and_custom_buttons(): void
    {
        $extension = new ButtonsExtension([
            ButtonType::COPY,
            Button::csv()
                ->text('Export CSV')
                ->className('btn btn-primary')
                ->exportOptions(['columns' => ':visible']),
            Button::excel()->option('filename', 'users-export'),
            Button::colVis()->text('Columns'),
        ]);

        $this->assertSame([
            [
                'extend'        => 'copy',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
            [
                'extend'        => 'csv',
                'text'          => 'Export CSV',
                'className'     => 'btn btn-primary',
                'exportOptions' => [
                    'columns' => ':visible',
                ],
            ],
            [
                'extend'        => 'excel',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
                'filename' => 'users-export',
            ],
            [
                'extend' => 'colvis',
                'text'   => 'Columns',
            ],
        ], $extension->jsonSerialize());
    }
}
