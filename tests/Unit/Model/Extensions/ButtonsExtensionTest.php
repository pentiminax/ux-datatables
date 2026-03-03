<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ButtonsExtension::class)]
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
}
