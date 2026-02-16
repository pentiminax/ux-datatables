<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use PHPUnit\Framework\TestCase;

class RelationFieldResolverTest extends TestCase
{
    public function testSimpleFieldReturnsAliasAndField(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->never())->method('leftJoin');

        $result = RelationFieldResolver::resolve($qb, 'e', 'name');

        $this->assertSame('e.name', $result);
    }

    public function testSingleLevelRelationAddsJoin(): void
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

    public function testMultiLevelRelationAddsMultipleJoins(): void
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

    public function testDuplicateJoinIsNotAdded(): void
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

    public function testPartialDuplicateJoinOnlyAddsNew(): void
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
