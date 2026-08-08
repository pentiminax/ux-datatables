<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\RowGroupExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowGroupExtension::class)]
final class RowGroupExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_a_property_data_source_with_defaults(): void
    {
        $extension = new RowGroupExtension(dataSrc: 'department');

        $this->assertSame('rowGroup', $extension->getKey());
        $this->assertSame([
            'dataSrc'        => 'department',
            'enable'         => true,
            'className'      => 'group',
            'startClassName' => 'group-start',
            'endClassName'   => 'group-end',
            'emptyDataGroup' => 'No group',
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_nested_grouping_and_custom_options(): void
    {
        $extension = new RowGroupExtension(
            dataSrc: ['department', 2],
            enable: false,
            className: 'table-group',
            startClassName: 'table-group-start',
            endClassName: 'table-group-end',
            emptyDataGroup: null,
        );

        $this->assertSame([
            'dataSrc'        => ['department', 2],
            'enable'         => false,
            'className'      => 'table-group',
            'startClassName' => 'table-group-start',
            'endClassName'   => 'table-group-end',
            'emptyDataGroup' => null,
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_is_added_to_the_datatable_payload_through_the_generic_extension_api(): void
    {
        $table = new DataTable('users');

        $table->addExtension(new RowGroupExtension(dataSrc: 1));

        $this->assertSame([
            'rowGroup' => [
                'dataSrc'        => 1,
                'enable'         => true,
                'className'      => 'group',
                'startClassName' => 'group-start',
                'endClassName'   => 'group-end',
                'emptyDataGroup' => 'No group',
            ],
        ], $table->getExtensions());
    }
}
