<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\RowMapper\Stage\ValueFormattingStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ValueFormattingStage::class)]
final class ValueFormattingStageTest extends TestCase
{
    #[Test]
    public function it_replaces_the_value_with_what_the_formatter_returns(): void
    {
        $columns = [TextColumn::new('customer')->formatValueUsing(
            static fn (?string $value, array $row): string => \sprintf('%s (%d)', $value, $row['id'])
        )];

        $result = (new ValueFormattingStage())->process(['customer' => 'Jane'], ['id' => 7], $columns);

        $this->assertSame(['customer' => 'Jane (7)'], $result);
    }

    #[Test]
    public function it_hands_the_source_item_of_a_row_context_to_the_formatter(): void
    {
        $context = new RowContext(source: ['id' => 7], item: ['projected' => true]);
        $columns = [TextColumn::new('customer')->formatValueUsing(
            static fn (mixed $value, array $row): string => 'source '.$row['id']
        )];

        $result = (new ValueFormattingStage())->process(['customer' => 'Jane'], $context, $columns);

        $this->assertSame(['customer' => 'source 7'], $result);
    }

    #[Test]
    public function it_formats_a_computed_column_that_the_row_does_not_carry(): void
    {
        $columns = [TextColumn::new('label')->formatValueUsing(
            static fn (mixed $value, array $row): string => 'Order '.$row['id']
        )];

        $result = (new ValueFormattingStage())->process([], ['id' => 7], $columns);

        $this->assertSame(['label' => 'Order 7'], $result);
    }

    #[Test]
    public function it_leaves_columns_without_a_formatter_alone(): void
    {
        $columns = [TextColumn::new('customer'), MoneyColumn::new('price')];

        $result = (new ValueFormattingStage())->process(['customer' => 'Jane', 'price' => 2500], [], $columns);

        $this->assertSame(['customer' => 'Jane', 'price' => 2500], $result);
    }
}
