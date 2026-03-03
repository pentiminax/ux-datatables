<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RelationFieldResolver::class)]
final class RelationFieldResolverTest extends TestCase
{
    #[Test]
    public function it_returns_alias_and_field_for_simple_field(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->never())->method('leftJoin');

        $result = RelationFieldResolver::resolve($qb, 'e', 'name');

        $this->assertSame('e.name', $result);
    }

    #[Test]
    public function it_adds_join_for_single_level_relation(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.author', 'author')
            ->willReturn($qb);

        $result = RelationFieldResolver::resolve($qb, 'e', 'author.firstName');

        $this->assertSame('author.firstName', $result);
    }

    #[Test]
    public function it_adds_multiple_joins_for_multi_level_relation(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $joinCalls = [];
        $qb->expects($this->exactly(2))
            ->method('leftJoin')
            ->willReturnCallback(function (string $join, string $alias) use ($qb, &$joinCalls) {
                $joinCalls[] = [$join, $alias];

                return $qb;
            });

        $result = RelationFieldResolver::resolve($qb, 'e', 'author.address.city');

        $this->assertSame('author_address.city', $result);
        $this->assertSame([
            ['e.author', 'author'],
            ['author.address', 'author_address'],
        ], $joinCalls);
    }

    #[Test]
    public function it_does_not_add_duplicate_join(): void
    {
        $existingJoin = $this->createMock(Join::class);
        $existingJoin->method('getAlias')->willReturn('author');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([
            'e' => [$existingJoin],
        ]);

        $qb->expects($this->never())->method('leftJoin');

        $result = RelationFieldResolver::resolve($qb, 'e', 'author.firstName');

        $this->assertSame('author.firstName', $result);
    }

    #[Test]
    public function it_only_adds_new_join_when_partial_duplicate(): void
    {
        $existingJoin = $this->createMock(Join::class);
        $existingJoin->method('getAlias')->willReturn('author');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([
            'e' => [$existingJoin],
        ]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('author.address', 'author_address')
            ->willReturn($qb);

        $result = RelationFieldResolver::resolve($qb, 'e', 'author.address.city');

        $this->assertSame('author_address.city', $result);
    }
}
