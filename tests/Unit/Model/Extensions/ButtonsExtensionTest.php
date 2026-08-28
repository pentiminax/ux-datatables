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
            Button::ccSearchClear()->text('Clear filters'),
            Button::custom('restoreOrder')->text('Restore order'),
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
            [
                'extend' => 'ccSearchClear',
                'text'   => 'Clear filters',
            ],
            [
                'action' => 'restoreOrder',
                'text'   => 'Restore order',
            ],
        ], $extension);
    }

    #[Test]
    public function it_adds_a_custom_button_via_the_fluent_helper(): void
    {
        $extension = (new ButtonsExtension([]))->withCustomButton('clearColumnControlFilters');

        $this->assertExtensionPayload([
            [
                'action' => 'clearColumnControlFilters',
            ],
        ], $extension);
    }

    #[Test]
    public function it_adds_a_columncontrol_search_clear_button_via_the_fluent_helper(): void
    {
        $extension = (new ButtonsExtension([]))->withCcSearchClearButton();

        $this->assertExtensionPayload(['ccSearchClear'], $extension);
    }

    #[Test]
    public function it_adds_a_collection_button_via_the_fluent_helper(): void
    {
        $extension = (new ButtonsExtension([]))->withCollectionButton(['colvis', 'csv']);

        $this->assertExtensionPayload([
            [
                'extend'  => 'collection',
                'buttons' => ['colvis', 'csv'],
            ],
        ], $extension);
    }

    #[Test]
    public function it_adds_a_server_side_csv_button_via_the_fluent_helper(): void
    {
        $extension = (new ButtonsExtension([]))->withCsvButton(serverSide: true);

        $this->assertExtensionPayload([
            [
                'action'    => Button::SERVER_EXPORT_ACTION,
                'format'    => 'csv',
                'exportKey' => 'csv',
            ],
        ], $extension);
    }

    #[Test]
    public function it_adds_a_server_side_xlsx_button_via_the_fluent_helper(): void
    {
        $extension = (new ButtonsExtension([]))->withExcelButton(serverSide: true);

        $this->assertExtensionPayload([
            [
                'action'    => Button::SERVER_EXPORT_ACTION,
                'format'    => 'xlsx',
                'exportKey' => 'xlsx',
            ],
        ], $extension);
    }

    #[Test]
    public function it_accepts_two_server_side_buttons_with_distinct_export_keys(): void
    {
        $extension = new ButtonsExtension([
            Button::csv(serverSide: true)->filename('all'),
            Button::csv(serverSide: true)->exportKey('subset')->filename('subset'),
        ]);

        $this->assertSame('all', $extension->findServerExportButton('csv')?->getFilename());
        $this->assertSame('subset', $extension->findServerExportButton('subset')?->getFilename());
        $this->assertSame('all', $extension->findServerExportButton(null)?->getFilename());
        $this->assertNull($extension->findServerExportButton('nope'));
    }

    #[Test]
    public function it_finds_a_server_side_button_nested_in_a_collection(): void
    {
        $extension = new ButtonsExtension([
            Button::collection([Button::excel(serverSide: true)->filename('report')]),
        ]);

        $this->assertTrue($extension->hasServerExportButton());
        $this->assertSame('report', $extension->findServerExportButton('xlsx')?->getFilename());
    }

    #[Test]
    public function it_reports_no_server_side_button_on_client_side_export_buttons(): void
    {
        $this->assertFalse((new ButtonsExtension([Button::csv(), Button::excel()]))->hasServerExportButton());
    }

    #[Test]
    public function it_rejects_two_server_side_buttons_sharing_an_export_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate server-side export key "csv".');

        new ButtonsExtension([
            Button::csv(serverSide: true)->filename('all'),
            Button::csv(serverSide: true)->filename('subset'),
        ]);
    }

    #[Test]
    public function it_rejects_a_duplicate_export_key_nested_in_a_collection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate server-side export key "xlsx".');

        new ButtonsExtension([
            Button::excel(serverSide: true),
            Button::collection([Button::excel(serverSide: true)]),
        ]);
    }

    #[Test]
    public function it_rejects_a_duplicate_export_key_added_through_the_fluent_helper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate server-side export key "csv".');

        (new ButtonsExtension([]))->withCsvButton(serverSide: true)->withCsvButton(serverSide: true);
    }
}
