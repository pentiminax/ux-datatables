<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\FixedColumnsExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FixedColumnsExtension::class)]
final class FixedColumnsExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_with_default_values(): void
    {
        $extension = new FixedColumnsExtension();

        $expectedArray = [
            'start' => 1,
            'end'   => 0,
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_with_custom_values(): void
    {
        $extension = new FixedColumnsExtension(start: 2, end: 1);

        $expectedArray = [
            'start' => 2,
            'end'   => 1,
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }
}
