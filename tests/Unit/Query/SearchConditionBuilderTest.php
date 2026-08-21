<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\SearchConditionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchConditionBuilder::class)]
final class SearchConditionBuilderTest extends TestCase
{
    #[Test]
    public function text_returns_ux_search_condition_and_sets_lower_cased_wrapped_parameter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('param_0', '%hello%');

        $result = SearchConditionBuilder::text($qb, 'e', 'name', 'hello', 'param_0');

        $this->assertSame('UX_DATATABLES_SEARCH(e.name, :param_0) = 1', $result);
    }

    #[Test]
    public function text_lower_cases_the_value_before_binding(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('param_0', '%john doe%');

        SearchConditionBuilder::text($qb, 'e', 'name', 'John Doe', 'param_0');
    }

    #[Test]
    public function numeric_returns_exact_condition_and_sets_raw_parameter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('param_0', '42');

        $result = SearchConditionBuilder::numeric($qb, 'e', 'id', '42', 'param_0');

        $this->assertSame('e.id = :param_0', $result);
    }

    #[Test]
    public function text_with_dot_notation_triggers_join(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.author', 'author')
            ->willReturn($qb);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('param_0', '%john%');

        $result = SearchConditionBuilder::text($qb, 'e', 'author.firstName', 'john', 'param_0');

        $this->assertSame('UX_DATATABLES_SEARCH(author.firstName, :param_0) = 1', $result);
    }

    #[Test]
    public function numeric_with_dot_notation_triggers_join(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.order', 'order')
            ->willReturn($qb);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('param_0', '99');

        $result = SearchConditionBuilder::numeric($qb, 'e', 'order.total', '99', 'param_0');

        $this->assertSame('order.total = :param_0', $result);
    }
}
