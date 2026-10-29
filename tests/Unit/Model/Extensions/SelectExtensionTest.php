<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\SelectItemType;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(SelectExtension::class)]
final class SelectExtensionTest extends DataTableTestCase
{
    #[Test]
    #[DataProvider('upstreamStyles')]
    public function it_serializes_every_upstream_select_style(SelectStyle $style, string $expected): void
    {
        $this->assertSame($expected, (new SelectExtension(style: $style))->jsonSerialize()['style']);
    }

    public static function upstreamStyles(): iterable
    {
        yield 'single' => [SelectStyle::SINGLE, 'single'];
        yield 'multi' => [SelectStyle::MULTI, 'multi'];
        yield 'os' => [SelectStyle::OS, 'os'];
        yield 'multi+shift' => [SelectStyle::MULTI_SHIFT, 'multi+shift'];
    }

    #[Test]
    public function it_serializes_default_options(): void
    {
        $this->assertExtensionPayload([
            'blurable'       => false,
            'className'      => 'selected',
            'info'           => true,
            'items'          => 'row',
            'keys'           => false,
            'style'          => 'single',
            'toggleable'     => true,
            'headerCheckbox' => false,
            'withCheckbox'   => false,
        ], new SelectExtension());
    }

    #[Test]
    public function it_serializes_configured_options_including_the_selector(): void
    {
        $extension = new SelectExtension(
            style: SelectStyle::MULTI,
            blurable: true,
            className: 'is-selected',
            info: false,
            items: SelectItemType::CELL,
            keys: true,
            selector: 'td:first-child',
            toggleable: false,
        );

        $extension->withCheckbox()->headerCheckbox();

        $this->assertExtensionPayload([
            'blurable'       => true,
            'className'      => 'is-selected',
            'info'           => false,
            'items'          => 'cell',
            'keys'           => true,
            'style'          => 'multi',
            'toggleable'     => false,
            'headerCheckbox' => true,
            'withCheckbox'   => true,
            'selector'       => 'td:first-child',
        ], $extension);
    }
}
