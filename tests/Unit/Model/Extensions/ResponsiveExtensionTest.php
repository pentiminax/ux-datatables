<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\ResponsiveExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ResponsiveExtension::class)]
final class ResponsiveExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_with_default_options(): void
    {
        $extension = new ResponsiveExtension();

        $this->assertSame([
            'auto'    => true,
            'details' => [
                'target' => 0,
                'type'   => 'inline',
            ],
            'orthogonal' => 'display',
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_custom_options(): void
    {
        $extension = new ResponsiveExtension(
            auto: false,
            detailsTarget: -1,
            detailsType: false,
            orthogonal: 'sort',
        );

        $this->assertSame([
            'auto'    => false,
            'details' => [
                'target' => -1,
                'type'   => false,
            ],
            'orthogonal' => 'sort',
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_omits_breakpoints_when_not_set(): void
    {
        $extension = new ResponsiveExtension();

        $this->assertArrayNotHasKey('breakpoints', $extension->jsonSerialize());
    }

    #[Test]
    public function it_includes_explicit_breakpoints(): void
    {
        $breakpoints = [
            ['name' => 'desktop', 'width' => 1200],
            ['name' => 'mobile', 'width' => 480],
        ];

        $extension = new ResponsiveExtension(breakpoints: $breakpoints);

        $this->assertSame($breakpoints, $extension->jsonSerialize()['breakpoints']);
    }
}
