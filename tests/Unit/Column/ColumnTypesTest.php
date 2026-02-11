<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\HtmlColumn;
use Pentiminax\UX\DataTables\Column\HtmlNumberColumn;
use Pentiminax\UX\DataTables\Column\HtmlNumberFormatColumn;
use Pentiminax\UX\DataTables\Column\HtmlUtf8Column;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\NumberFormatColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\Utf8TextColumn;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColumnTypesTest extends TestCase
{
    public function testDefaultTitleFallsBackToName(): void
    {
        $data = TextColumn::new('username')->jsonSerialize();

        $this->assertArrayHasKey('title', $data);
        $this->assertSame('username', $data['title']);
    }

    public function testExportableFlagTweaksCssClass(): void
    {
        $data = TextColumn::new('username')
            ->setClassName('text-bold')
            ->setTitle('Username')
            ->setExportable(false)
            ->jsonSerialize();

        $this->assertSame('text-bold not-exportable', $data['className']);
    }

    public function testRenderAndDefaultContentAreExposed(): void
    {
        $data = TextColumn::new('username')
            ->setTitle('Username')
            ->setRender('renderFn')
            ->setDefaultContent('N/A')
            ->jsonSerialize();

        $this->assertSame('renderFn', $data['render']);
        $this->assertSame('N/A', $data['defaultContent']);
    }

    #[DataProvider('provideColumns')]
    public function testColumnTypesMatchEnum(AbstractColumn $column, ColumnType $expectedType): void
    {
        $column->setTitle('Column Title');

        $data = $column->jsonSerialize();

        $this->assertSame($expectedType->value, $data['type']);
    }

    /**
     * @return iterable<string, array{0: AbstractColumn, 1: ColumnType}>
     */
    public static function provideColumns(): iterable
    {
        yield 'text' => [TextColumn::new('col_text'), ColumnType::STRING];
        yield 'text-utf8' => [Utf8TextColumn::new('col_utf8'), ColumnType::STRING_UTF8];
        yield 'boolean' => [BooleanColumn::new('col_bool'), ColumnType::NUM];
        yield 'date' => [DateColumn::new('col_date'), ColumnType::DATE];
        yield 'number' => [NumberColumn::new('col_number'), ColumnType::NUM];
        yield 'number-formatted' => [NumberFormatColumn::new('col_num_fmt'), ColumnType::NUM_FMT];
        yield 'html-number' => [HtmlNumberColumn::new('col_html_num'), ColumnType::HTML_NUM];
        yield 'html-number-formatted' => [HtmlNumberFormatColumn::new('col_html_num_fmt'), ColumnType::HTML_NUM_FMT];
        yield 'html' => [HtmlColumn::new('col_html'), ColumnType::HTML];
        yield 'html-utf8' => [HtmlUtf8Column::new('col_html_utf8'), ColumnType::HTML_UTF8];
    }
}
