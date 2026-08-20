<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

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
        yield 'export button gets default export options' => [
            Button::excel(),
            [
                'extend'        => 'excel',
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ],
        ];

        yield 'plain column visibility is a string' => [Button::colVis(), 'colvis'];

        yield 'customized column visibility is an object without export options' => [
            Button::colVis()->text('Columns'),
            [
                'extend' => 'colvis',
                'text'   => 'Columns',
            ],
        ];

        yield 'plain columncontrol search clear is a string' => [
            Button::ccSearchClear(),
            'ccSearchClear',
        ];

        yield 'customized columncontrol search clear is an object without export options' => [
            Button::ccSearchClear()->text('Clear filters'),
            [
                'extend' => 'ccSearchClear',
                'text'   => 'Clear filters',
            ],
        ];
    }
}
