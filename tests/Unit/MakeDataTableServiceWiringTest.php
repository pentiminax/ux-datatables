<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Pentiminax\UX\DataTables\Maker\MakeDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\MakerAppKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTablesBundle::class)]
#[CoversClass(MakeDataTable::class)]
final class MakeDataTableServiceWiringTest extends TestCase
{
    #[Test]
    public function test_give_n_bundle_kernel_with_maker_whe_n_make_data_table_service_is_resolved_the_n_service_is_instantiated(): void
    {
        $kernel = new MakerAppKernel('test', true);

        $kernel->boot();

        $service = $kernel->getContainer()->get('test.datatables.maker.datatable');

        self::assertInstanceOf(MakeDataTable::class, $service);

        $kernel->shutdown();
    }
}
