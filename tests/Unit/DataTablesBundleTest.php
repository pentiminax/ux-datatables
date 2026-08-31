<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Export\CsvExporter;
use Pentiminax\UX\DataTables\Export\ExporterRegistry;
use Pentiminax\UX\DataTables\Export\XlsxExporter;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Tests\Support\BootsTwigKernel;
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
    use BootsTwigKernel;

    #[Test]
    public function it_wires_the_query_intent_factory_through_the_datatable_infrastructure(): void
    {
        $infrastructure = $this->kernel->getContainer()->get('test.datatables.infrastructure');

        self::assertArrayHasKey('DataTablesBundle', $this->kernel->getBundles());
        self::assertInstanceOf(DataTableInfrastructure::class, $infrastructure);
        self::assertInstanceOf(DefaultDataTableQueryIntentFactory::class, $infrastructure->queryIntentFactory);
    }

    /**
     * Autoconfigured tables are tagged `kernel.reset`, so worker runtimes
     * (FrankenPHP worker mode, Swoole, RoadRunner) get the per-request isolation
     * PHP-FPM gets from a fresh process.
     */
    #[Test]
    public function it_resets_shared_data_tables_between_requests(): void
    {
        /** @var AbstractDataTable $table */
        $table = $this->kernel->getContainer()->get('test.datatables.server_side_template');

        $configured = $table->getDataTable();

        $this->container->get('services_resetter')->reset();

        self::assertNotSame($configured, $table->getDataTable());
    }

    #[Test]
    public function it_wires_one_exporter_per_supported_export_format(): void
    {
        $registry = $this->kernel->getContainer()->get('test.datatables.export.registry');

        self::assertInstanceOf(ExporterRegistry::class, $registry);
        self::assertInstanceOf(CsvExporter::class, $registry->get(ExportFormat::CSV));
        self::assertInstanceOf(XlsxExporter::class, $registry->get(ExportFormat::XLSX));
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
