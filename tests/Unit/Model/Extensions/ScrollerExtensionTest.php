<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\ScrollerExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ScrollerExtension::class)]
final class ScrollerExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_with_default_options(): void
    {
        $extension = new ScrollerExtension();

        $this->assertSame([
            'boundaryScale' => 0.5,
            'displayBuffer' => 9,
            'rowHeight'     => 'auto',
            'serverWait'    => 200,
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_custom_options(): void
    {
        $extension = new ScrollerExtension(
            boundaryScale: 0.75,
            displayBuffer: 12,
            rowHeight: 32,
            serverWait: 500,
        );

        $this->assertSame([
            'boundaryScale' => 0.75,
            'displayBuffer' => 12,
            'rowHeight'     => 32,
            'serverWait'    => 500,
        ], $extension->jsonSerialize());
    }
}
