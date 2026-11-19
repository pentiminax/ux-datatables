<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\DataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTable::class)]
final class DataTableColumnsTypeTest extends TestCase
{
    #[Test]
    public function columns_stores_abstract_columns_by_name(): void
    {
        $table = (new DataTable('t'))->columns([TextColumn::new('a'), TextColumn::new('b')]);

        $this->assertNotNull($table->getColumnByName('a'));
        $this->assertNotNull($table->getColumnByName('b'));
    }

    #[Test]
    public function columns_rejects_a_column_that_is_not_an_abstract_column(): void
    {
        $this->expectException(\TypeError::class);

        (new DataTable('t'))->columns([$this->createStub(ColumnInterface::class)]);
    }
}
