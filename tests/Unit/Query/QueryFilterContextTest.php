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

    #[Test]
    public function it_returns_the_same_index_for_the_same_reference_within_one_scope(): void
    {
        $column    = TextColumn::new('name', 'Name')->setField('name');
        $context   = $this->context(new DataTableRequest(1, new Columns([])), [$column]);
        $reference = $context->intent->columns[0];

        // A legacy filter building one bound parameter referenced by more than one DQL
        // fragment calls paramIndexFor($reference) more than once expecting the same
        // placeholder name back both times -- otherwise the Doctrine placeholder and the
        // later setParameter() call diverge, leaving the query with an unbound parameter.
        $this->assertSame(0, $context->paramIndexFor($reference));
        $this->assertSame(0, $context->paramIndexFor($reference));
        $this->assertSame(0, $context->paramIndexFor($reference));
    }

    #[Test]
    public function it_draws_distinct_indices_for_distinct_references_within_one_scope(): void
    {
        $columnA = TextColumn::new('name', 'Name')->setField('name');
        $columnB = TextColumn::new('email', 'Email')->setField('email');
        $context = $this->context(new DataTableRequest(1, new Columns([])), [$columnA, $columnB]);

        [$referenceA, $referenceB] = $context->intent->columns;

        $this->assertSame(0, $context->paramIndexFor($referenceA));
        $this->assertSame(1, $context->paramIndexFor($referenceB));
        $this->assertSame(0, $context->paramIndexFor($referenceA));
    }

    #[Test]
    public function it_shares_the_counter_between_next_param_index_and_param_index_for(): void
    {
        $column    = TextColumn::new('name', 'Name')->setField('name');
        $context   = $this->context(new DataTableRequest(1, new Columns([])), [$column]);
        $reference = $context->intent->columns[0];

        $this->assertSame(0, $context->nextParamIndex());
        $this->assertSame(1, $context->paramIndexFor($reference));
        $this->assertSame(1, $context->paramIndexFor($reference));
        $this->assertSame(2, $context->nextParamIndex());
    }

    #[Test]
    public function it_forgets_param_index_for_stability_after_reset_param_index_scope(): void
    {
        $column    = TextColumn::new('name', 'Name')->setField('name');
        $context   = $this->context(new DataTableRequest(1, new Columns([])), [$column]);
        $reference = $context->intent->columns[0];

        // QueryFilterPipeline calls this between filters. A second filter processing
        // the same column after the reset must draw a genuinely fresh index -- this is the
        // guarantee that keeps two different filters from colliding on the same column,
        // even though each one individually sees stable, repeatable indices.
        $this->assertSame(0, $context->paramIndexFor($reference));
        $context->resetParamIndexScope();
        $this->assertSame(1, $context->paramIndexFor($reference));
        $this->assertSame(1, $context->paramIndexFor($reference));
    }
}
