<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Button::class)]
final class ButtonTest extends TestCase
{
    #[Test]
    public function it_serializes_custom_button_options(): void
    {
        $button = Button::csv()
            ->text('Export CSV')
            ->className('btn btn-sm btn-outline-primary')
            ->exportOptions([
                'columns'  => ':visible',
                'modifier' => [
                    'page' => 'current',
                ],
            ])
            ->option('filename', 'users-export');

        $this->assertSame([
            'extend'        => 'csv',
            'text'          => 'Export CSV',
            'className'     => 'btn btn-sm btn-outline-primary',
            'exportOptions' => [
                'columns'  => ':visible',
                'modifier' => [
                    'page' => 'current',
                ],
            ],
            'filename' => 'users-export',
        ], $button->jsonSerialize());
    }

    #[Test]
    #[DataProvider('provideSerializationVariants')]
    public function it_serializes_button_variants(Button $button, array|string $expected): void
    {
        $this->assertSame($expected, $button->jsonSerialize());
    }

    /**
     * @return iterable<string, array{Button, array<string, mixed>|string}>
     */
    public static function provideSerializationVariants(): iterable
    {
        yield 'plain csv is the client-side export' => [
            Button::csv(),
            [
                'extend'        => 'csv',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
                'text' => 'CSV',
            ],
        ];

        yield 'export button gets default export options' => [
            Button::excel(),
            [
                'extend'        => 'excel',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
                'text' => 'Excel',
            ],
        ];

        yield 'plain column visibility has a default text' => [
            Button::colVis(),
            [
                'extend' => 'colvis',
                'text'   => 'Column Visibility',
            ],
        ];

        yield 'customized column visibility is an object without export options' => [
            Button::colVis()->text('Columns'),
            [
                'extend' => 'colvis',
                'text'   => 'Columns',
            ],
        ];

        yield 'plain columncontrol search clear has a default text' => [
            Button::ccSearchClear(),
            [
                'extend' => 'ccSearchClear',
                'text'   => 'Clear Search',
            ],
        ];

        yield 'customized columncontrol search clear is an object without export options' => [
            Button::ccSearchClear()->text('Clear filters'),
            [
                'extend' => 'ccSearchClear',
                'text'   => 'Clear filters',
            ],
        ];

        yield 'collection with no options is still an object, not a bare string' => [
            Button::collection([]),
            [
                'extend'  => 'collection',
                'buttons' => [],
            ],
        ];

        yield 'custom action button has no extend or export options' => [
            Button::custom('restoreOrder')->text('Restore order')->className('btn btn-sm'),
            [
                'action'    => 'restoreOrder',
                'text'      => 'Restore order',
                'className' => 'btn btn-sm',
            ],
        ];

        yield 'server-side csv serializes as a custom action' => [
            Button::csv(serverSide: true)->text('Export CSV')->filename('users'),
            [
                'action'    => Button::SERVER_EXPORT_ACTION,
                'format'    => 'csv',
                'exportKey' => 'csv',
                'text'      => 'Export CSV',
                'filename'  => 'users',
            ],
        ];
    }

    #[Test]
    public function it_serializes_a_collection_with_nested_button_objects_and_raw_strings(): void
    {
        $button = Button::collection([Button::csv(), 'colvis'])->text('Export');

        $this->assertSame([
            'extend'  => 'collection',
            'buttons' => [
                [
                    'extend'        => 'csv',
                    'exportOptions' => ['columns' => ':visible:not(.not-exportable)'],
                    'text'          => 'CSV',
                ],
                'colvis',
            ],
            'text' => 'Export',
        ], json_decode(json_encode($button), true));
    }

    #[Test]
    public function it_serializes_a_server_side_csv_nested_inside_a_collection(): void
    {
        $button = Button::collection([Button::csv(serverSide: true)->filename('users')])->text('Export');

        $this->assertSame([
            'extend'  => 'collection',
            'buttons' => [
                [
                    'action'    => Button::SERVER_EXPORT_ACTION,
                    'format'    => 'csv',
                    'exportKey' => 'csv',
                    'text'      => 'CSV',
                    'filename'  => 'users',
                ],
            ],
            'text' => 'Export',
        ], json_decode(json_encode($button), true));
    }

    #[Test]
    public function it_serializes_a_server_side_xlsx_button(): void
    {
        $button = Button::excel(serverSide: true)->filename('users');

        $this->assertSame(ExportFormat::XLSX, $button->getExportFormat());
        $this->assertTrue($button->isServerSideExport());
        $this->assertSame('xlsx', $button->getExportKey());
        $this->assertSame([
            'action'    => Button::SERVER_EXPORT_ACTION,
            'format'    => 'xlsx',
            'exportKey' => 'xlsx',
            'text'      => 'Excel',
            'filename'  => 'users',
        ], json_decode(json_encode($button), true));
    }

    #[Test]
    public function it_keeps_client_side_export_buttons_untouched(): void
    {
        foreach ([Button::csv(), Button::excel()] as $button) {
            $this->assertNull($button->getExportFormat());
            $this->assertFalse($button->isServerSideExport());
        }

        $this->assertSame([
            'extend'        => 'excel',
            'exportOptions' => ['columns' => ':visible:not(.not-exportable)'],
            'text'          => 'Excel',
        ], json_decode(json_encode(Button::excel()), true));
    }

    #[Test]
    public function it_keeps_an_explicit_export_key(): void
    {
        $button = Button::excel(serverSide: true)->exportKey('  full-report  ');

        $this->assertSame('full-report', $button->getExportKey());
    }

    #[Test]
    public function it_rejects_an_empty_custom_action_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom button action must not be empty.');

        Button::custom('   ');
    }

    #[Test]
    public function it_rejects_serializing_a_custom_button_built_without_an_action(): void
    {
        $button = Button::fromType(ButtonType::CUSTOM)->text('Restore order');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A custom button must have an "action" name set. Use Button::custom().');

        $button->jsonSerialize();
    }

    #[Test]
    public function it_serializes_a_custom_button_nested_inside_a_collection_option(): void
    {
        $colVis = Button::colVis()
            ->text('Columns')
            ->option('postfixButtons', [
                ['extend' => 'colvisRestore'],
                Button::custom('restoreOrder')->text('Restore order'),
            ]);

        $this->assertSame([
            'extend'         => 'colvis',
            'text'           => 'Columns',
            'postfixButtons' => [
                ['extend' => 'colvisRestore'],
                ['action' => 'restoreOrder', 'text' => 'Restore order'],
            ],
        ], json_decode(json_encode($colVis), true));
    }
}
