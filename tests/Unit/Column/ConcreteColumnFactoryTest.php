<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
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
        yield 'boolean' => [BooleanColumn::class, ColumnType::NUM];
        yield 'date' => [DateColumn::class, ColumnType::DATE];
        yield 'number' => [NumberColumn::class, ColumnType::NUM];
        yield 'template' => [TemplateColumn::class, ColumnType::HTML];
        yield 'url' => [UrlColumn::class, ColumnType::HTML];
        yield 'email' => [EmailColumn::class, ColumnType::HTML];
    }

    /**
     * @return iterable<string, array{0: callable(): AbstractColumn, 1: ColumnType}>
     */
    public static function provideVariantBuilders(): iterable
    {
        yield 'text::utf8' => [static fn () => TextColumn::new('field_name', 'Field label')->utf8(), ColumnType::STRING_UTF8];
        yield 'text::html' => [static fn () => TextColumn::new('field_name', 'Field label')->html(), ColumnType::HTML];
        yield 'text::html+utf8' => [static fn () => TextColumn::new('field_name', 'Field label')->html()->utf8(), ColumnType::HTML_UTF8];
        yield 'number::formatted' => [static fn () => NumberColumn::new('field_name', 'Field label')->formatted(), ColumnType::NUM_FMT];
        yield 'number::html' => [static fn () => NumberColumn::new('field_name', 'Field label')->html(), ColumnType::HTML_NUM];
        yield 'number::html+formatted' => [static fn () => NumberColumn::new('field_name', 'Field label')->html()->formatted(), ColumnType::HTML_NUM_FMT];
    }

    #[Test]
    #[DataProvider('provideVariantBuilders')]
    public function it_creates_columns_via_variant_modifiers(callable $builder, ColumnType $expectedType): void
    {
        $column = $builder();

        $data = $column->jsonSerialize();

        $this->assertSame('field_name', $data['data']);
        $this->assertSame('field_name', $data['name']);
        $this->assertSame('Field label', $data['title']);
        $this->assertSame($expectedType->value, $data['type']);
    }

    #[Test]
    public function it_does_not_add_implicit_custom_options_in_new(): void
    {
        $this->assertArrayNotHasKey('customOptions', BooleanColumn::new('active')->jsonSerialize());
        $this->assertArrayNotHasKey('customOptions', DateColumn::new('createdAt')->jsonSerialize());
    }

    #[Test]
    public function text_modifiers_are_idempotent_and_order_independent(): void
    {
        $fromHtmlThenUtf8  = TextColumn::new('field_name')->html()->utf8()->jsonSerialize();
        $fromUtf8ThenHtml  = TextColumn::new('field_name')->utf8()->html()->jsonSerialize();
        $fromRepeatedCalls = TextColumn::new('field_name')->html()->utf8()->html()->utf8()->jsonSerialize();

        $this->assertSame(ColumnType::HTML_UTF8->value, $fromHtmlThenUtf8['type']);
        $this->assertSame($fromHtmlThenUtf8['type'], $fromUtf8ThenHtml['type']);
        $this->assertSame($fromHtmlThenUtf8['type'], $fromRepeatedCalls['type']);
    }

    #[Test]
    public function number_modifiers_are_idempotent_and_order_independent(): void
    {
        $fromHtmlThenFormatted = NumberColumn::new('field_name')->html()->formatted()->jsonSerialize();
        $fromFormattedThenHtml = NumberColumn::new('field_name')->formatted()->html()->jsonSerialize();
        $fromRepeatedCalls     = NumberColumn::new('field_name')->html()->formatted()->html()->formatted()->jsonSerialize();

        $this->assertSame(ColumnType::HTML_NUM_FMT->value, $fromHtmlThenFormatted['type']);
        $this->assertSame($fromHtmlThenFormatted['type'], $fromFormattedThenHtml['type']);
        $this->assertSame($fromHtmlThenFormatted['type'], $fromRepeatedCalls['type']);
    }
}
