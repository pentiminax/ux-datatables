<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\Button;
use PHPUnit\Framework\Attributes\CoversClass;
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
    public function it_applies_default_export_options_to_export_buttons(): void
    {
        $this->assertSame([
            'extend'        => 'excel',
            'exportOptions' => [
                'columns' => ':visible:not(.not-exportable)',
            ],
        ], Button::excel()->jsonSerialize());
    }

    #[Test]
    public function it_serializes_plain_column_visibility_as_a_string(): void
    {
        $this->assertSame('colvis', Button::colVis()->jsonSerialize());
    }

    #[Test]
    public function it_serializes_customized_column_visibility_as_an_object(): void
    {
        $this->assertSame([
            'extend' => 'colvis',
            'text'   => 'Columns',
        ], Button::colVis()->text('Columns')->jsonSerialize());
    }
}
