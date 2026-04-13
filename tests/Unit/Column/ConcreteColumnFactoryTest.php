<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Column\HtmlColumn;
use Pentiminax\UX\DataTables\Column\HtmlNumberColumn;
use Pentiminax\UX\DataTables\Column\HtmlNumberFormatColumn;
use Pentiminax\UX\DataTables\Column\HtmlUtf8Column;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\NumberFormatColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Column\Utf8TextColumn;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractColumn::class)]
final class ConcreteColumnFactoryTest extends TestCase
{
    #[Test]
    #[DataProvider('provideColumns')]
    public function it_sets_name_data_title_and_type(string $columnClass, ColumnType $expectedType): void
    {
        $column = $columnClass::new('field_name', 'Field label');
        if ($column instanceof TemplateColumn) {
            $column->setTemplate('datatable/columns/cell.html.twig');
        }

        $data = $column->jsonSerialize();

        $this->assertSame('field_name', $data['data']);
        $this->assertSame('field_name', $data['name']);
        $this->assertSame('Field label', $data['title']);
        $this->assertSame($expectedType->value, $data['type']);
    }

    #[Test]
    #[DataProvider('provideColumns')]
    public function it_defaults_title_to_name(string $columnClass): void
    {
        $column = $columnClass::new('field_name');
        if ($column instanceof TemplateColumn) {
            $column->setTemplate('datatable/columns/cell.html.twig');
        }

        $data = $column->jsonSerialize();

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
        yield 'template' => [TemplateColumn::class, ColumnType::HTML];
        yield 'url' => [UrlColumn::class, ColumnType::HTML];
        yield 'email' => [EmailColumn::class, ColumnType::HTML];
    }

    /**
     * @return iterable<string, array{0: callable(): AbstractColumn, 1: ColumnType}>
     */
    public static function provideConsolidatedFactories(): iterable
    {
        yield 'text::utf8' => [static fn () => TextColumn::utf8('field_name', 'Field label'), ColumnType::STRING_UTF8];
        yield 'text::html' => [static fn () => TextColumn::html('field_name', 'Field label'), ColumnType::HTML];
        yield 'text::htmlUtf8' => [static fn () => TextColumn::htmlUtf8('field_name', 'Field label'), ColumnType::HTML_UTF8];
        yield 'number::formatted' => [static fn () => NumberColumn::formatted('field_name', 'Field label'), ColumnType::NUM_FMT];
        yield 'number::html' => [static fn () => NumberColumn::html('field_name', 'Field label'), ColumnType::HTML_NUM];
        yield 'number::htmlFormatted' => [static fn () => NumberColumn::htmlFormatted('field_name', 'Field label'), ColumnType::HTML_NUM_FMT];
    }

    #[Test]
    #[DataProvider('provideConsolidatedFactories')]
    public function it_creates_columns_via_consolidated_factories(callable $factory, ColumnType $expectedType): void
    {
        $column = $factory();

        $data = $column->jsonSerialize();

        $this->assertSame('field_name', $data['data']);
        $this->assertSame('field_name', $data['name']);
        $this->assertSame('Field label', $data['title']);
        $this->assertSame($expectedType->value, $data['type']);
    }
}
