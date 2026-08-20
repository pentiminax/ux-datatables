<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Query\ColumnSearchResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColumnSearchResolver::class)]
final class ColumnSearchResolverTest extends TestCase
{
    #[Test]
    public function resolve_field_returns_search_field_when_set(): void
    {
        $column = TextColumn::new('donorProviderName')->setSearchField('donorProvider.name');

        $this->assertSame('donorProvider.name', ColumnSearchResolver::resolveField($column));
    }

    #[Test]
    public function resolve_field_falls_back_to_get_field_when_search_field_is_null(): void
    {
        $column = TextColumn::new('name')->setField('name');

        $this->assertSame('name', ColumnSearchResolver::resolveField($column));
    }

    #[Test]
    public function resolve_field_returns_column_name_as_fallback_when_no_explicit_field(): void
    {
        // AbstractColumn::getField() returns $name when $field is null
        $column = TextColumn::new('email');

        $this->assertSame('email', ColumnSearchResolver::resolveField($column));
    }

    #[Test]
    public function apply_search_joins_is_noop_when_column_has_no_joins(): void
    {
        $column = TextColumn::new('name');
        $qb     = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('leftJoin');
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        ColumnSearchResolver::applySearchJoins($qb, $column);
    }

    #[Test]
    public function apply_search_joins_adds_join_without_condition(): void
    {
        $column = TextColumn::new('donorProviderName')->addSearchJoin('e.donorProvider', 'dp');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.donorProvider', 'dp')
            ->willReturn($qb);

        ColumnSearchResolver::applySearchJoins($qb, $column);
    }

    #[Test]
    public function apply_search_joins_adds_join_with_condition(): void
    {
        $column = TextColumn::new('name')
            ->addSearchJoin('e.tags', 'tag', 'WITH', 'tag.active = true');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.tags', 'tag', 'WITH', 'tag.active = true')
            ->willReturn($qb);

        ColumnSearchResolver::applySearchJoins($qb, $column);
    }

    #[Test]
    public function apply_search_joins_skips_existing_alias(): void
    {
        $column = TextColumn::new('donorProviderName')->addSearchJoin('e.donorProvider', 'dp');

        $existingJoin = $this->createMock(Join::class);
        $existingJoin->method('getAlias')->willReturn('dp');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn(['e' => [$existingJoin]]);
        $qb->expects($this->never())->method('leftJoin');

        ColumnSearchResolver::applySearchJoins($qb, $column);
    }

    #[Test]
    public function apply_search_joins_adds_only_new_aliases_in_multi_join_scenario(): void
    {
        $column = TextColumn::new('name')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->addSearchJoin('dp.address', 'dp_addr');

        $existingJoin = $this->createMock(Join::class);
        $existingJoin->method('getAlias')->willReturn('dp');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn(['e' => [$existingJoin]]);
        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('dp.address', 'dp_addr')
            ->willReturn($qb);

        ColumnSearchResolver::applySearchJoins($qb, $column);
    }
}
