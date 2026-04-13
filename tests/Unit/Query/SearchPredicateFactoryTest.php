<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Query\SearchPredicateFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchPredicateFactory::class)]
final class SearchPredicateFactoryTest extends TestCase
{
    #[Test]
    public function it_returns_like_condition_for_text_column(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with('p_0', '%hello%');

        $column = TextColumn::new('name', 'Name')->setField('name');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'name', 'hello', 'p_0');

        $this->assertSame('e.name LIKE :p_0', $result);
    }

    #[Test]
    public function it_returns_null_for_text_column_with_association_field(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with('client')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Project']);
        $qb->method('getEntityManager')->willReturn($em);
        $qb->expects($this->never())->method('setParameter');

        $column = TextColumn::new('client', 'Client')->setField('client');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'client', 'acme', 'p_0');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_exact_condition_for_numeric_column_with_numeric_value(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with('p_0', '42');

        $column = NumberColumn::new('id', 'ID')->setField('id');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'id', '42', 'p_0');

        $this->assertSame('e.id = :p_0', $result);
    }

    #[Test]
    public function it_returns_null_for_numeric_column_with_non_numeric_value(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('setParameter');

        $column = NumberColumn::new('id', 'ID')->setField('id');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'id', 'abc', 'p_0');

        $this->assertNull($result);
    }
}
