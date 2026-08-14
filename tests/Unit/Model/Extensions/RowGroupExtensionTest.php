<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\RowGroupExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowGroupExtension::class)]
final class RowGroupExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function payloadProvider(): iterable
    {
        yield 'property data source with defaults' => [
            ['dataSrc' => 'department'],
            [
                'dataSrc'        => 'department',
                'enable'         => true,
                'className'      => 'group',
                'startClassName' => 'group-start',
                'endClassName'   => 'group-end',
                'emptyDataGroup' => 'No group',
            ],
        ];

        yield 'nested grouping and custom options' => [
            [
                'dataSrc'        => ['department', 2],
                'enable'         => false,
                'className'      => 'table-group',
                'startClassName' => 'table-group-start',
                'endClassName'   => 'table-group-end',
                'emptyDataGroup' => null,
            ],
            [
                'dataSrc'        => ['department', 2],
                'enable'         => false,
                'className'      => 'table-group',
                'startClassName' => 'table-group-start',
                'endClassName'   => 'table-group-end',
                'emptyDataGroup' => null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('payloadProvider')]
    public function it_serializes_the_row_group_options(array $arguments, array $expected): void
    {
        $extension = new RowGroupExtension(...$arguments);

        $this->assertSame($expected, $extension->jsonSerialize());
    }

    #[Test]
    public function it_is_added_to_the_datatable_payload_through_the_generic_extension_api(): void
    {
        $table     = new DataTable('users');
        $extension = new RowGroupExtension(dataSrc: 1);

        $table->addExtension($extension);

        $this->assertSame('rowGroup', $extension->getKey());
        $this->assertSame(['rowGroup' => $extension->jsonSerialize()], $table->getExtensions());
    }
}
