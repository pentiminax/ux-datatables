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
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(AbstractColumn::class)]
final class ConcreteColumnFactoryTest extends DataTableTestCase
{
    /**
     * @param callable(): AbstractColumn $builder
     */
    #[Test]
    #[DataProvider('provideColumnBuilders')]
    public function it_sets_name_data_title_and_type(callable $builder, ColumnType $expectedType): void
    {
        $this->assertColumnHeader($builder(), $expectedType->value, 'field_name', 'Field label');
    }

    /**
     * @return iterable<string, array{0: callable(): AbstractColumn, 1: ColumnType}>
     */
    public static function provideColumnBuilders(): iterable
    {
        yield 'text' => [static fn () => TextColumn::new('field_name', 'Field label'), ColumnType::STRING];
        yield 'boolean' => [static fn () => BooleanColumn::new('field_name', 'Field label'), ColumnType::NUM];
        yield 'date' => [static fn () => DateColumn::new('field_name', 'Field label'), ColumnType::DATE];
        yield 'number' => [static fn () => NumberColumn::new('field_name', 'Field label'), ColumnType::NUM];
        yield 'template' => [static fn () => TemplateColumn::new('field_name', 'Field label')->setTemplate('datatable/columns/cell.html.twig'), ColumnType::HTML];
        yield 'url' => [static fn () => UrlColumn::new('field_name', 'Field label'), ColumnType::HTML];
        yield 'email' => [static fn () => EmailColumn::new('field_name', 'Field label'), ColumnType::HTML];
        yield 'text::utf8' => [static fn () => TextColumn::new('field_name', 'Field label')->utf8(), ColumnType::STRING_UTF8];
        yield 'text::html' => [static fn () => TextColumn::new('field_name', 'Field label')->html(), ColumnType::HTML];
        yield 'text::html+utf8' => [static fn () => TextColumn::new('field_name', 'Field label')->html()->utf8(), ColumnType::HTML_UTF8];
        yield 'number::formatted' => [static fn () => NumberColumn::new('field_name', 'Field label')->formatted(), ColumnType::NUM_FMT];
        yield 'number::html' => [static fn () => NumberColumn::new('field_name', 'Field label')->html(), ColumnType::HTML_NUM];
        yield 'number::html+formatted' => [static fn () => NumberColumn::new('field_name', 'Field label')->html()->formatted(), ColumnType::HTML_NUM_FMT];
    }

    #[Test]
    public function it_does_not_add_implicit_custom_options_in_new(): void
    {
        $this->assertArrayNotHasKey('customOptions', BooleanColumn::new('active')->jsonSerialize());
        $this->assertArrayNotHasKey('customOptions', DateColumn::new('createdAt')->jsonSerialize());
    }

    /**
     * @param list<callable(): AbstractColumn> $builders
     */
    #[Test]
    #[DataProvider('provideEquivalentModifierChains')]
    public function modifiers_are_idempotent_and_order_independent(array $builders, ColumnType $expectedType): void
    {
        foreach ($builders as $builder) {
            $this->assertSame($expectedType->value, $builder()->jsonSerialize()['type']);
        }
    }

    /**
     * @return iterable<string, array{0: list<callable(): AbstractColumn>, 1: ColumnType}>
     */
    public static function provideEquivalentModifierChains(): iterable
    {
        yield 'text' => [[
            static fn () => TextColumn::new('field_name')->html()->utf8(),
            static fn () => TextColumn::new('field_name')->utf8()->html(),
            static fn () => TextColumn::new('field_name')->html()->utf8()->html()->utf8(),
        ], ColumnType::HTML_UTF8];

        yield 'number' => [[
            static fn () => NumberColumn::new('field_name')->html()->formatted(),
            static fn () => NumberColumn::new('field_name')->formatted()->html(),
            static fn () => NumberColumn::new('field_name')->html()->formatted()->html()->formatted(),
        ], ColumnType::HTML_NUM_FMT];
    }
}
