<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\FixedHeaderExtension;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * @internal
 */
#[CoversClass(FixedHeaderExtension::class)]
final class FixedHeaderExtensionTest extends DataTableTestCase
{
    /**
     * @param array{header?: bool, footer?: bool, headerOffset?: int, footerOffset?: int} $arguments
     * @param array{header: bool, footer: bool, headerOffset: int, footerOffset: int}     $expected
     */
    #[Test]
    #[TestWith([
        [],
        ['header' => true, 'footer' => false, 'headerOffset' => 0, 'footerOffset' => 0],
    ], 'default values')]
    #[TestWith([
        ['header' => false, 'footer' => true, 'headerOffset' => 40, 'footerOffset' => 20],
        ['header' => false, 'footer' => true, 'headerOffset' => 40, 'footerOffset' => 20],
    ], 'custom values')]
    public function it_serializes_the_fixed_header_options(array $arguments, array $expected): void
    {
        $extension = new FixedHeaderExtension(...$arguments);

        $this->assertExtensionPayload($expected, $extension);
    }

    #[Test]
    public function it_is_added_to_the_datatable_payload_through_the_generic_extension_api(): void
    {
        $table     = new DataTable('users');
        $extension = new FixedHeaderExtension(footer: true);

        $table->addExtension($extension);

        $this->assertSame('fixedHeader', $extension->getKey());
        $this->assertSame(['fixedHeader' => $extension->jsonSerialize()], $table->getExtensions());
    }
}
