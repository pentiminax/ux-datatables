<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\Summary\Average;
use Pentiminax\UX\DataTables\Model\Summary\Count;
use Pentiminax\UX\DataTables\Model\Summary\Max;
use Pentiminax\UX\DataTables\Model\Summary\Min;
use Pentiminax\UX\DataTables\Model\Summary\Sum;
use Pentiminax\UX\DataTables\Model\Summary\Summarizer;
use Pentiminax\UX\DataTables\Model\Summary\SummaryType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractColumn::class)]
#[CoversClass(SummaryType::class)]
final class ColumnSummaryTest extends TestCase
{
    #[Test]
    public function a_column_without_summary_serializes_no_summaries_key(): void
    {
        $this->assertArrayNotHasKey('summaries', MoneyColumn::new('total')->jsonSerialize());
    }

    #[Test]
    public function it_serializes_summarizers_with_optional_labels(): void
    {
        $column = MoneyColumn::new('total')->summarize(Sum::new('Revenue'), Average::new(), Min::new(), Max::new(), Count::new());

        $this->assertSame([
            ['type' => 'sum', 'label' => 'Revenue'],
            ['type' => 'avg'],
            ['type' => 'min'],
            ['type' => 'max'],
            ['type' => 'count'],
        ], $column->jsonSerialize()['summaries']);
    }

    #[Test]
    public function a_second_summarizer_of_the_same_type_replaces_the_first(): void
    {
        $column = MoneyColumn::new('total')->summarize(Sum::new('A'))->summarize(Sum::new('B'));

        $this->assertSame([['type' => 'sum', 'label' => 'B']], $column->jsonSerialize()['summaries']);
    }

    #[Test]
    public function only_count_accepts_a_non_numeric_column(): void
    {
        $this->assertCount(1, DateColumn::new('createdAt')->summarize(Count::new())->getSummarizers());
        $this->assertCount(1, TextColumn::new('name')->summarize(Count::new())->getSummarizers());
    }

    #[Test]
    #[DataProvider('numericOnlySummarizers')]
    public function min_and_max_on_a_date_column_throw(Summarizer $summarizer): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DateColumn::new('createdAt')->summarize($summarizer);
    }

    /**
     * @return iterable<string, array{Summarizer}>
     */
    public static function numericOnlySummarizers(): iterable
    {
        yield 'min' => [new Min()];
        yield 'max' => [new Max()];
        yield 'average' => [new Average()];
    }

    #[Test]
    public function sum_on_a_non_numeric_column_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "sum" summary requires a numeric column, but "name" is not numeric.');

        TextColumn::new('name')->summarize(Sum::new());
    }

    #[Test]
    public function average_on_a_date_column_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DateColumn::new('createdAt')->summarize(Average::new());
    }

    #[Test]
    public function a_template_column_cannot_be_summarized(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The column "badge" cannot be summarized because it renders no data field.');

        TemplateColumn::new('badge')->summarize(Count::new());
    }

    #[Test]
    public function an_action_column_cannot_be_summarized(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ActionColumn::fromActions('actions', 'Actions', new Actions())->summarize(Count::new());
    }

    #[Test]
    public function it_aggregates_in_memory_ignoring_nulls(): void
    {
        $values = [10, '5.5', null, 4.5];

        $this->assertSame(20.0, SummaryType::Sum->aggregate($values));
        $this->assertEqualsWithDelta(6.6666, SummaryType::Average->aggregate($values), 0.001);
        $this->assertSame(4.5, SummaryType::Min->aggregate($values));
        $this->assertSame(10, SummaryType::Max->aggregate($values));
        $this->assertNull(SummaryType::Sum->aggregate([null]));
    }

    #[Test]
    public function count_counts_every_non_null_value(): void
    {
        $this->assertSame(3, SummaryType::Count->aggregate(['a', '', 0, null]));
        $this->assertSame(0, SummaryType::Count->aggregate([]));
    }
}
