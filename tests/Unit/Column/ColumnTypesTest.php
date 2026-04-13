<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColumnType::class)]
final class ColumnTypesTest extends TestCase
{
    #[Test]
    public function it_falls_back_to_name_as_title(): void
    {
        $data = TextColumn::new('username')->jsonSerialize();

        $this->assertArrayHasKey('title', $data);
        $this->assertSame('username', $data['title']);
    }

    #[Test]
    public function it_adds_not_exportable_css_class(): void
    {
        $data = TextColumn::new('username')
            ->setClassName('text-bold')
            ->setTitle('Username')
            ->setExportable(false)
            ->jsonSerialize();

        $this->assertSame('text-bold not-exportable', $data['className']);
    }

    #[Test]
    public function it_exposes_render_and_default_content(): void
    {
        $data = TextColumn::new('username')
            ->setTitle('Username')
            ->setRender('renderFn')
            ->setDefaultContent('N/A')
            ->jsonSerialize();

        $this->assertSame('renderFn', $data['render']);
        $this->assertSame('N/A', $data['defaultContent']);
    }

    #[Test]
    #[DataProvider('provideColumns')]
    public function it_matches_column_types_to_enum(AbstractColumn $column, ColumnType $expectedType): void
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
        yield 'text-utf8' => [TextColumn::utf8('col_utf8'), ColumnType::STRING_UTF8];
        yield 'boolean' => [BooleanColumn::new('col_bool'), ColumnType::NUM];
        yield 'date' => [DateColumn::new('col_date'), ColumnType::DATE];
        yield 'number' => [NumberColumn::new('col_number'), ColumnType::NUM];
        yield 'number-formatted' => [NumberColumn::formatted('col_num_fmt'), ColumnType::NUM_FMT];
        yield 'html-number' => [NumberColumn::html('col_html_num'), ColumnType::HTML_NUM];
        yield 'html-number-formatted' => [NumberColumn::htmlFormatted('col_html_num_fmt'), ColumnType::HTML_NUM_FMT];
        yield 'html' => [TextColumn::html('col_html'), ColumnType::HTML];
        yield 'html-utf8' => [TextColumn::htmlUtf8('col_html_utf8'), ColumnType::HTML_UTF8];
        yield 'template' => [TemplateColumn::new('col_template')->setTemplate('datatable/columns/cell.html.twig'), ColumnType::HTML];
        yield 'url' => [UrlColumn::new('col_url'), ColumnType::HTML];
        yield 'choice' => [ChoiceColumn::new('col_choice'), ColumnType::HTML];
        yield 'email' => [EmailColumn::new('col_email'), ColumnType::HTML];
    }
}
