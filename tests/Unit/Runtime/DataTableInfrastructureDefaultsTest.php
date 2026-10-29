<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataTableDefaultsConfiguratorInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTableInfrastructure::class)]
final class DataTableInfrastructureDefaultsTest extends TestCase
{
    #[Test]
    public function configurators_run_in_order_on_top_of_the_yaml_defaults(): void
    {
        $infrastructure = DataTableInfrastructure::createDefault(
            options: ['pageLength' => 25],
            defaultsConfigurators: [
                self::configurator(static fn (DataTable $table) => $table->pageLength(50)),
                self::configurator(static fn (DataTable $table) => $table->pageLength(75)),
            ],
        );

        self::assertSame(75, $infrastructure->createDataTable('a')->getOption('pageLength'));
    }

    #[Test]
    public function each_table_gets_its_own_instance_and_the_table_keeps_the_last_word(): void
    {
        $infrastructure = DataTableInfrastructure::createDefault(
            defaultsConfigurators: [self::configurator(static fn (DataTable $table) => $table->pageLength(50))],
        );

        $first  = $infrastructure->createDataTable('a')->pageLength(10);
        $second = $infrastructure->createDataTable('b');

        self::assertSame(10, $first->getOption('pageLength'));
        self::assertSame(50, $second->getOption('pageLength'));
    }

    #[Test]
    public function an_abstract_data_table_keeps_the_last_word_over_the_configurators(): void
    {
        $table = new class extends AbstractDataTable {
            public function configureDataTable(DataTable $table): DataTable
            {
                return $table->pageLength(10);
            }

            public function configureColumns(): iterable
            {
                yield TextColumn::new('id');
            }
        };

        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            defaultsConfigurators: [self::configurator(static fn (DataTable $table) => $table->pageLength(50)->stateSave(true))],
        ));

        self::assertSame(10, $table->getConfiguredDataTable()->getOption('pageLength'));
        self::assertTrue($table->getConfiguredDataTable()->getOption('stateSave'));
    }

    private static function configurator(\Closure $apply): DataTableDefaultsConfiguratorInterface
    {
        return new class($apply) implements DataTableDefaultsConfiguratorInterface {
            public function __construct(private readonly \Closure $apply)
            {
            }

            public function configure(DataTable $table): void
            {
                ($this->apply)($table);
            }
        };
    }
}
