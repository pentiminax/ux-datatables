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
    public function it_serializes_to_true(): void
    {
        $extension = new ScrollerExtension();

        $this->assertTrue($extension->jsonSerialize());
    }
}
