<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\ColReorderExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColReorderExtension::class)]
final class ColReorderExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_with_default_options(): void
    {
        $extension = new ColReorderExtension();

        $this->assertSame([
            'enable'  => true,
            'columns' => '',
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_starting_disabled_with_a_restricted_column_selector(): void
    {
        $extension = new ColReorderExtension(enable: false, columns: ':not(:first-child)');

        $this->assertSame([
            'enable'  => false,
            'columns' => ':not(:first-child)',
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_omits_header_rows_and_order_when_not_set(): void
    {
        $extension = new ColReorderExtension();
        $payload   = $extension->jsonSerialize();

        $this->assertArrayNotHasKey('headerRows', $payload);
        $this->assertArrayNotHasKey('order', $payload);
    }

    #[Test]
    public function it_serializes_restricted_header_rows_and_an_initial_order(): void
    {
        $extension = new ColReorderExtension(headerRows: [0], order: [2, 0, 1]);

        $this->assertSame([
            'enable'     => true,
            'columns'    => '',
            'headerRows' => [0],
            'order'      => [2, 0, 1],
        ], $extension->jsonSerialize());
    }
}
