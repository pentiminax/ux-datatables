<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Pentiminax\UX\DataTables\Mutation\BulkRecords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BulkRecords::class)]
final class BulkRecordsTest extends TestCase
{
    #[Test]
    public function it_counts_the_selected_records_not_the_yielded_ones(): void
    {
        $records = new BulkRecords(static fn (): \Generator => yield new \stdClass(), 5);

        $this->assertCount(5, $records);
    }

    #[Test]
    public function it_only_pulls_what_the_consumer_asks_for(): void
    {
        $pulled = 0;

        $records = new BulkRecords(static function () use (&$pulled): \Generator {
            foreach (range(1, 100) as $ignored) {
                ++$pulled;

                yield new \stdClass();
            }
        }, 100);

        $records->first();

        $this->assertSame(1, $pulled);
    }

    #[Test]
    public function it_refuses_a_second_iteration(): void
    {
        $records = new BulkRecords(static fn (): \Generator => yield new \stdClass(), 1);
        $records->toArray();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('BulkRecords can only be iterated once.');

        $records->toArray();
    }

    #[Test]
    public function it_maps_and_filters_over_the_yielded_entities(): void
    {
        $first  = new \stdClass();
        $second = new \stdClass();

        $first->keep  = true;
        $second->keep = false;

        $records = new BulkRecords(static function () use ($first, $second): \Generator {
            yield $first;
            yield $second;
        }, 2);

        $this->assertSame([true, false], $records->map(static fn (object $e): bool => $e->keep));
    }

    #[Test]
    public function an_empty_selection_reports_itself_as_empty(): void
    {
        $records = new BulkRecords(static fn (): \Generator => yield from [], 0);

        $this->assertTrue($records->isEmpty());
    }
}
