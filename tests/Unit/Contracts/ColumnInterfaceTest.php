<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Contracts;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class ColumnInterfaceTest extends TestCase
{
    /**
     * @return iterable<string, array{ColumnInterface}>
     */
    public static function provideConcretColumns(): iterable
    {
        yield 'TextColumn' => [TextColumn::new('name', 'Name')];
        yield 'NumberColumn' => [NumberColumn::new('price', 'Price')];
        yield 'DateColumn' => [DateColumn::new('created_at', 'Created at')];
        yield 'BooleanColumn' => [BooleanColumn::new('active', 'Active')];
        yield 'EmailColumn' => [EmailColumn::new('email', 'Email')];
    }

    #[Test]
    #[DataProvider('provideConcretColumns')]
    public function it_satisfies_column_interface(ColumnInterface $column): void
    {
        $this->assertInstanceOf(ColumnInterface::class, $column);
    }

    #[Test]
    #[DataProvider('provideConcretColumns')]
    public function it_exposes_readable_state_via_interface(ColumnInterface $column): void
    {
        $this->assertInstanceOf(ColumnType::class, $column->getType());
        $this->assertIsBool($column->isVisible());
        $this->assertIsBool($column->isOrderable());
        $this->assertIsBool($column->isSearchable());
        $this->assertIsBool($column->isGlobalSearchable());
        $this->assertIsBool($column->isExportable());
        $this->assertIsBool($column->isNumber());
        $this->assertIsBool($column->isDate());
        $this->assertIsString($column->getName());
        $this->assertIsArray($column->getCustomOptions());
        $this->assertNull($column->getCustomOption('nonexistent'));
    }

    #[Test]
    public function it_reflects_mutations_through_interface_getters(): void
    {
        $column = TextColumn::new('title', 'Title')
            ->setVisible(false)
            ->setOrderable(false)
            ->setSearchable(false)
            ->disableGlobalSearch()
            ->setExportable(false)
            ->setWidth('200px')
            ->setClassName('col-title')
            ->setCellType('th')
            ->setRender('renderFn')
            ->setDefaultContent('N/A');

        $column->setCustomOption('highlight', true);

        $interface = $column;

        $this->assertFalse($interface->isVisible());
        $this->assertFalse($interface->isOrderable());
        $this->assertFalse($interface->isSearchable());
        $this->assertFalse($interface->isGlobalSearchable());
        $this->assertFalse($interface->isExportable());
        $this->assertSame('200px', $interface->getWidth());
        $this->assertSame('col-title', $interface->getClassName());
        $this->assertSame('th', $interface->getCellType());
        $this->assertSame('renderFn', $interface->getRender());
        $this->assertSame('N/A', $interface->getDefaultContent());
        $this->assertSame(['highlight' => true], $interface->getCustomOptions());
        $this->assertTrue($interface->getCustomOption('highlight'));
    }

    #[Test]
    public function it_correctly_identifies_numeric_column_type(): void
    {
        $col = NumberColumn::new('amount', 'Amount');

        $this->assertTrue($col->isNumber());
        $this->assertFalse($col->isDate());
    }

    #[Test]
    public function it_correctly_identifies_date_column_type(): void
    {
        $col = DateColumn::new('created_at', 'Created at');

        $this->assertTrue($col->isDate());
        $this->assertFalse($col->isNumber());
    }
}
