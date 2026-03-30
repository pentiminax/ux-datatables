<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Pentiminax\UX\DataTables\Maker\MakeDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\MakerAppKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataTablesBundle::class)]
#[CoversClass(MakeDataTable::class)]
final class MakeDataTableServiceWiringTest extends TestCase
{
    #[Test]
    public function test_GIVEN_bundleKernelWithMaker_WHEN_makeDataTableServiceIsResolved_THEN_serviceIsInstantiated(): void
    {
        $kernel = new MakerAppKernel('test', true);

        $kernel->boot();

        $service = $kernel->getContainer()->get('test.datatables.maker.datatable');

        self::assertInstanceOf(MakeDataTable::class, $service);

        $kernel->shutdown();
    }
}
