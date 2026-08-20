<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Tests\Support\BuildsQueryFilterContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryFilterContext::class)]
final class QueryFilterContextTest extends TestCase
{
    use BuildsQueryFilterContext;

    #[Test]
    public function it_resolves_a_configured_column_by_name(): void
    {
        $column  = TextColumn::new('name', 'Name')->setField('name');
        $context = $this->context(new DataTableRequest(1, new Columns([])), [$column]);

        $this->assertSame($column, $context->columnByName('name'));
    }

    #[Test]
    public function it_returns_null_for_an_unconfigured_column_name(): void
    {
        $context = $this->context(new DataTableRequest(1, new Columns([])), []);

        $this->assertNull($context->columnByName('missing'));
    }

    #[Test]
    public function it_defaults_the_root_alias_to_e(): void
    {
        $context = $this->context(new DataTableRequest(1, new Columns([])), []);

        $this->assertSame('e', $context->alias);
    }

    #[Test]
    public function it_hands_out_a_fresh_never_repeated_index_on_every_call(): void
    {
        $context = $this->context(new DataTableRequest(1, new Columns([])), []);

        $this->assertSame(0, $context->nextParamIndex());
        $this->assertSame(1, $context->nextParamIndex());
        $this->assertSame(2, $context->nextParamIndex());
    }

    #[Test]
    public function it_shares_one_counter_across_the_lifetime_of_the_context_regardless_of_caller(): void
    {
        $context = $this->context(new DataTableRequest(1, new Columns([])), []);

        // Two independent "callers" (e.g. two different filters) pulling indices from the
        // same context must never see the same number twice between them -- this is the
        // exact guarantee that keeps ColumnSearchFilter's and ColumnControlSearchFilter's
        // parameter names from colliding on the same column.
        $first  = [$context->nextParamIndex(), $context->nextParamIndex()];
        $second = [$context->nextParamIndex(), $context->nextParamIndex()];

        $this->assertSame([0, 1], $first);
        $this->assertSame([2, 3], $second);
    }

    /**
     * @deprecated coverage: paramIndexFor() must stay callable for a custom
     * QueryFilterInterface implementation that predates nextParamIndex() and never got
     * updated -- an undefined-method error is worse than a deprecated method that still works
     */
    #[Test]
    public function it_keeps_param_index_for_callable_and_drawing_from_the_shared_counter(): void
    {
        $column    = TextColumn::new('name', 'Name')->setField('name');
        $context   = $this->context(new DataTableRequest(1, new Columns([])), [$column]);
        $reference = $context->intent->columns[0];

        // paramIndexFor() no longer returns a value stable per reference -- it now draws
        // from the exact same counter as nextParamIndex(), so a legacy caller can never
        // collide with a built-in filter processing the same column, at the cost of no
        // longer getting the same number back for the same reference on a second call.
        $this->assertSame(0, $context->paramIndexFor($reference));
        $this->assertSame(1, $context->nextParamIndex());
        $this->assertSame(2, $context->paramIndexFor($reference));
    }
}
