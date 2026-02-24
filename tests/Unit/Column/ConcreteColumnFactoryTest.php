<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\HtmlColumn;
use Pentiminax\UX\DataTables\Column\HtmlNumberColumn;
use Pentiminax\UX\DataTables\Column\HtmlNumberFormatColumn;
use Pentiminax\UX\DataTables\Column\HtmlUtf8Column;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\NumberFormatColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Column\Utf8TextColumn;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConcreteColumnFactoryTest extends TestCase
{
    #[DataProvider('provideColumns')]
    public function testFactorySetsNameDataTitleAndType(string $columnClass, ColumnType $expectedType): void
    {
        $column = $columnClass::new('field_name', 'Field label');
        $data   = $column->jsonSerialize();

        $this->assertSame('field_name', $data['data']);
        $this->assertSame('field_name', $data['name']);
        $this->assertSame('Field label', $data['title']);
        $this->assertSame($expectedType->value, $data['type']);
    }

    #[DataProvider('provideColumns')]
    public function testFactoryDefaultsTitleToName(string $columnClass): void
    {
        $column = $columnClass::new('field_name');
        $data   = $column->jsonSerialize();

        $this->assertSame('field_name', $data['title']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: ColumnType}>
     */
    public static function provideColumns(): iterable
    {
        yield 'text' => [TextColumn::class, ColumnType::STRING];
        yield 'text-utf8' => [Utf8TextColumn::class, ColumnType::STRING_UTF8];
        yield 'boolean' => [BooleanColumn::class, ColumnType::NUM];
        yield 'date' => [DateColumn::class, ColumnType::DATE];
        yield 'number' => [NumberColumn::class, ColumnType::NUM];
        yield 'number-formatted' => [NumberFormatColumn::class, ColumnType::NUM_FMT];
        yield 'html-number' => [HtmlNumberColumn::class, ColumnType::HTML_NUM];
        yield 'html-number-formatted' => [HtmlNumberFormatColumn::class, ColumnType::HTML_NUM_FMT];
        yield 'html' => [HtmlColumn::class, ColumnType::HTML];
        yield 'html-utf8' => [HtmlUtf8Column::class, ColumnType::HTML_UTF8];
        yield 'url' => [UrlColumn::class, ColumnType::HTML];
    }
}
