<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Export;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Export\ExportValueFormatter;
use Pentiminax\UX\DataTables\Tests\Unit\Column\TestStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(ExportValueFormatter::class)]
final class ExportValueFormatterTest extends TestCase
{
    #[Test]
    public function it_writes_money_in_currency_units_as_a_number(): void
    {
        $formatter = new ExportValueFormatter();

        $two   = $formatter->formatRow([MoneyColumn::new('price')], ['price' => 2500]);
        $three = $formatter->formatRow([MoneyColumn::new('price')->decimals(3)], ['price' => 2500]);

        $this->assertSame(25.0, $two['price']);
        $this->assertSame(2.5, $three['price']);
    }

    #[Test]
    public function it_converts_a_decimal_string_stored_as_cents(): void
    {
        $row = (new ExportValueFormatter())->formatRow([MoneyColumn::new('price')], ['price' => '1999']);

        $this->assertSame(19.99, $row['price']);
    }

    #[Test]
    public function it_leaves_money_that_is_not_stored_as_cents(): void
    {
        $row = (new ExportValueFormatter())->formatRow([MoneyColumn::new('price')->storedAsCents(false)], ['price' => 25.5]);

        $this->assertSame(25.5, $row['price']);
    }

    #[Test]
    public function it_puts_the_currency_in_the_money_heading(): void
    {
        $formatter = new ExportValueFormatter();

        $this->assertSame('Price (USD)', $formatter->heading(MoneyColumn::new('price')->currency('USD'), 'Price'));
        $this->assertSame('Name', $formatter->heading(TextColumn::new('name'), 'Name'));
    }

    #[Test]
    public function it_writes_the_label_of_a_choice(): void
    {
        $formatter = new ExportValueFormatter();
        $column    = ChoiceColumn::new('status')->setChoices(['Draft' => 'draft']);

        $row = $formatter->formatRow([$column, ChoiceColumn::new('state')->setChoices(TestStatus::class)], [
            'status' => 'draft',
            'state'  => 'active',
        ]);

        $this->assertSame(['status' => 'Draft', 'state' => 'Active'], $row);
    }

    #[Test]
    public function it_keeps_a_choice_value_without_a_label(): void
    {
        $column = ChoiceColumn::new('status')->setChoices(['Draft' => 'draft']);

        $row = (new ExportValueFormatter())->formatRow([$column], ['status' => 'archived']);

        $this->assertSame('archived', $row['status']);
    }

    #[Test]
    public function it_writes_a_boolean_as_yes_or_no(): void
    {
        $row = (new ExportValueFormatter())->formatRow(
            [BooleanColumn::new('active'), BooleanColumn::new('archived'), BooleanColumn::new('unknown')],
            ['active' => true, 'archived' => 0, 'unknown' => null],
        );

        $this->assertSame(['active' => 'Yes', 'archived' => 'No', 'unknown' => null], $row);
    }

    #[Test]
    public function it_translates_the_boolean_labels(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => 'fr:'.$id,
        );

        $row = (new ExportValueFormatter($translator))->formatRow([BooleanColumn::new('active')], ['active' => true]);

        $this->assertSame('fr:export.boolean.true', $row['active']);
    }

    #[Test]
    public function it_leaves_a_column_formatted_by_format_value_using(): void
    {
        $formatter = new ExportValueFormatter();
        $column    = MoneyColumn::new('price')->formatValueUsing(static fn (mixed $value): string => '€ 25.00');

        $row = $formatter->formatRow([$column], ['price' => '€ 25.00']);

        $this->assertSame('€ 25.00', $row['price']);
        $this->assertSame('Price', $formatter->heading($column, 'Price'));
    }
}
