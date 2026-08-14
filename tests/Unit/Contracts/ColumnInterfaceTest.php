<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Contracts;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
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
    #[Test]
    #[DataProvider('provideConcretColumns')]
    public function it_returns_null_for_an_unknown_custom_option(ColumnInterface $column): void
    {
        $this->assertArrayNotHasKey('nonexistent', $column->getCustomOptions());
        $this->assertNull($column->getCustomOption('nonexistent'));
    }

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
    #[DataProvider('provideColumnKindFlags')]
    public function it_identifies_number_and_date_columns(ColumnInterface $column, bool $isNumber, bool $isDate): void
    {
        $this->assertSame($isNumber, $column->isNumber());
        $this->assertSame($isDate, $column->isDate());
    }

    /**
     * @return iterable<string, array{ColumnInterface, bool, bool}>
     */
    public static function provideColumnKindFlags(): iterable
    {
        yield 'number' => [NumberColumn::new('amount', 'Amount'), true, false];
        yield 'date' => [DateColumn::new('created_at', 'Created at'), false, true];
        yield 'text' => [TextColumn::new('name', 'Name'), false, false];
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
            ->setDefaultContent('N/A');

        $column->setCustomOption('highlight', true);

        $this->assertFalse($column->isVisible());
        $this->assertFalse($column->isOrderable());
        $this->assertFalse($column->isSearchable());
        $this->assertFalse($column->isGlobalSearchable());
        $this->assertFalse($column->isExportable());
        $this->assertSame('200px', $column->getWidth());
        $this->assertSame('col-title', $column->getClassName());
        $this->assertSame('th', $column->getCellType());
        $this->assertSame('N/A', $column->getDefaultContent());
        $this->assertSame(['highlight' => true], $column->getCustomOptions());
        $this->assertTrue($column->getCustomOption('highlight'));
    }
}
