<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\FixedColumnsExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FixedColumnsExtension::class)]
final class FixedColumnsExtensionTest extends TestCase
{
    /**
     * @param array{start?: int, end?: int} $arguments
     * @param array{start: int, end: int}   $expected
     */
    #[Test]
    #[TestWith([[], ['start' => 1, 'end' => 0]], 'default values')]
    #[TestWith([['start' => 2, 'end' => 1], ['start' => 2, 'end' => 1]], 'custom values')]
    public function it_serializes_the_fixed_column_counts(array $arguments, array $expected): void
    {
        $extension = new FixedColumnsExtension(...$arguments);

        $this->assertSame($expected, $extension->jsonSerialize());
    }
}
