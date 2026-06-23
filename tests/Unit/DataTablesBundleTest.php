<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTablesBundle::class)]
final class DataTablesBundleTest extends TestCase
{
    #[Test]
    public function it_boots_kernel(): void
    {
        $kernel = new TwigAppKernel('test', true);

        $kernel->boot();

        $this->assertArrayHasKey('DataTablesBundle', $kernel->getBundles());

        $kernel->shutdown();
    }

    #[Test]
    public function it_wires_the_query_intent_factory_through_the_datatable_infrastructure(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();

        $infrastructure = $kernel->getContainer()->get('test.datatables.infrastructure');

        self::assertInstanceOf(DataTableInfrastructure::class, $infrastructure);
        self::assertInstanceOf(DefaultDataTableQueryIntentFactory::class, $infrastructure->queryIntentFactory());

        $kernel->shutdown();
    }
}
