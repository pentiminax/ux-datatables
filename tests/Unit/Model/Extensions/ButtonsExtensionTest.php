<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(ButtonsExtension::class)]
#[CoversClass(Button::class)]
final class ButtonsExtensionTest extends DataTableTestCase
{
    #[Test]
    public function it_serializes_strings_enum_cases_and_custom_buttons(): void
    {
        $extension = new ButtonsExtension([
            'colvis',
            'pdf',
            ButtonType::COPY,
            Button::csv()
                ->text('Export CSV')
                ->className('btn btn-primary')
                ->exportOptions(['columns' => ':visible']),
            Button::excel()->option('filename', 'users-export'),
            Button::colVis()->text('Columns'),
        ]);

        $this->assertExtensionPayload([
            'colvis',
            [
                'extend'        => 'pdf',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
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
        ], $extension);
    }
}
