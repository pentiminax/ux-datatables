<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\ConfigDefaultsAppKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableConfigDefaultsTest extends TestCase
{
    private ConfigDefaultsAppKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = new ConfigDefaultsAppKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    #[Test]
    public function it_inherits_the_bundle_configuration_defaults(): void
    {
        $table = $this->configuredDataTable();

        $expectedPaging = [
            'boundaryNumbers' => true,
            'buttons'         => 5,
            'firstLast'       => false,
            'numbers'         => true,
            'previousNext'    => true,
        ];

        $paging = $table->getOption('paging');

        self::assertEquals($expectedPaging, $paging);
        self::assertTrue($table->getOptions()['paging']);
        self::assertSame(['paging' => $paging], $table->getOptions()['layout']['bottomEnd']);
        self::assertSame(25, $table->getOption('pageLength'));
        self::assertSame(['class' => 'table table-striped'], $table->getAttributes());
        self::assertSame('multi', $table->getExtensions()['select']['style'] ?? null);
    }

    #[Test]
    public function per_table_configuration_still_wins_over_the_bundle_defaults(): void
    {
        $table = $this->configuredDataTable();

        $table->paging(buttons: 3);

        self::assertSame(3, $table->getOption('paging')['buttons']);
    }

    private function configuredDataTable(): DataTable
    {
        /** @var AbstractDataTable $dataTable */
        $dataTable = $this->kernel->getContainer()->get('test.datatables.config_defaults');

        return $dataTable->getConfiguredDataTable();
    }
}
