<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTable::class)]
final class ColumnContractDeprecationTest extends TestCase
{
    use CollectsDeprecations;

    #[Test]
    public function it_deprecates_a_column_that_does_not_extend_abstract_column_added_to_a_table(): void
    {
        $column = $this->directImplementer();

        $deprecations = $this->deprecationsOf(static fn () => (new DataTable('users'))->add($column));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('does not extend', $deprecations[0]);
        $this->assertStringContainsString('AbstractColumn', $deprecations[0]);
    }

    #[Test]
    public function it_deprecates_such_a_column_given_to_columns(): void
    {
        $column = $this->directImplementer();

        $deprecations = $this->deprecationsOf(static fn () => (new DataTable('users'))->columns([$column]));

        $this->assertCount(1, $deprecations);
    }

    #[Test]
    public function it_deprecates_such_a_column_once_when_configure_columns_returns_it(): void
    {
        $table = new ConfigurableDataTable([$this->directImplementer()]);

        $deprecations = $this->withoutAuthorizationFallback($this->deprecationsOf(static fn () => $table->getDataTable()));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('does not extend', $deprecations[0]);
    }

    #[Test]
    public function it_deprecates_a_column_added_in_configure_data_table_once(): void
    {
        $column = $this->directImplementer();
        $table  = new class($column) extends AbstractDataTable {
            public function __construct(private readonly ColumnInterface $column)
            {
            }

            public function configureDataTable(DataTable $table): DataTable
            {
                return $table->add($this->column);
            }
        };

        $deprecations = $this->withoutAuthorizationFallback($this->deprecationsOf(static fn () => $table->getDataTable()));

        $this->assertCount(1, $deprecations);
    }

    #[Test]
    public function it_does_not_deprecate_bundled_columns(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);

        $deprecations = $this->deprecationsOf(static function () use ($table): void {
            (new DataTable('users'))->add(TextColumn::new('name'))->columns([TextColumn::new('id')]);
            $table->getDataTable();
        });

        $this->assertSame([], $this->withoutAuthorizationFallback($deprecations));
    }

    private function directImplementer(): ColumnInterface
    {
        $column = $this->createStub(ColumnInterface::class);
        $column->method('getName')->willReturn('custom');

        return $column;
    }
}
