<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableRowPresentationTest extends TestCase
{
    #[Test]
    public function set_data_emits_the_row_class_and_record_url_for_client_side_rows(): void
    {
        $table = new RowPresentationTestTable();

        $table->setData([['id' => 1, 'late' => true], ['id' => 2, 'late' => false]]);

        $this->assertSame([
            ['id' => 1, 'late' => true, 'DT_RowClass' => 'table-danger', 'DT_RowAttr' => ['data-record-url' => '/items/1']],
            ['id' => 2, 'late' => false, 'DT_RowAttr' => ['data-record-url' => '/items/2']],
        ], $table->getDataTable()->getOption('data'));
        $this->assertTrue($table->getDataTable()->getOptions()['recordUrl']);
    }
}

final class RowPresentationTestTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->rowClass(static fn (array $row): ?string => $row['late'] ? 'table-danger' : null)
            ->recordUrl(static fn (array $row): string => '/items/'.$row['id']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
