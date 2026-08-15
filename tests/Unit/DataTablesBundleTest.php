<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(DataTablesBundle::class)]
final class DataTablesBundleTest extends TestCase
{
    private TwigAppKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TwigAppKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    #[Test]
    public function it_wires_the_query_intent_factory_through_the_datatable_infrastructure(): void
    {
        $infrastructure = $this->kernel->getContainer()->get('test.datatables.infrastructure');

        self::assertArrayHasKey('DataTablesBundle', $this->kernel->getBundles());
        self::assertInstanceOf(DataTableInfrastructure::class, $infrastructure);
        self::assertInstanceOf(DefaultDataTableQueryIntentFactory::class, $infrastructure->queryIntentFactory());
    }

    #[Test]
    public function it_registers_the_filter_bar_translation_catalog(): void
    {
        /** @var TranslatorInterface $translator */
        $translator = $this->kernel->getContainer()->get('translator');

        self::assertSame('All', $translator->trans('filter.bar.all', [], FilterLabels::DOMAIN, 'en'));
        self::assertSame('Tous', $translator->trans('filter.bar.all', [], FilterLabels::DOMAIN, 'fr'));
    }
}
