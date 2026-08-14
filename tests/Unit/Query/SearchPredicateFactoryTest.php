<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
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
    use BuildsTypedFieldQueryBuilder;

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
        $qb->expects($this->once())->method('setParameter')->with('p_0', '42', null);

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

    #[Test]
    public function it_returns_exact_condition_for_non_numeric_column_when_forcing_numeric(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with('p_0', '42', null);

        $column = TextColumn::new('score', 'Score')->setField('score');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'score', '42', 'p_0', true);

        $this->assertSame('e.score = :p_0', $result);
    }

    #[Test]
    public function it_returns_null_when_forcing_numeric_with_non_numeric_value(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('setParameter');

        $column = TextColumn::new('score', 'Score')->setField('score');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'score', 'abc', 'p_0', true);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_non_text_field(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with('active')->willReturn(false);
        $metadata->method('hasField')->with('active')->willReturn(true);
        $metadata->method('getFieldMapping')->with('active')->willReturn(new FieldMapping('boolean', 'active', 'active'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->with('App\\Entity\\User')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\User']);
        $qb->method('getEntityManager')->willReturn($em);
        $qb->expects($this->never())->method('setParameter');

        $column = TextColumn::new('active', 'Active')->setField('active');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'active', 'true', 'p_0');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_exact_condition_for_guid_column_with_uuid_value(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'guid');
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with('p_0', '018f2c3e-1234-7abc-9def-0123456789ab', 'guid');

        $column = TextColumn::new('id', 'ID')->setField('id');
        $result = SearchPredicateFactory::build(
            $qb,
            $column,
            'e',
            'id',
            '018f2c3e-1234-7abc-9def-0123456789ab',
            'p_0',
        );

        $this->assertSame('e.id = :p_0', $result);
    }

    #[Test]
    public function it_returns_null_for_guid_column_with_partial_text_value(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'guid');
        $qb->expects($this->never())->method('setParameter');

        $column = TextColumn::new('id', 'ID')->setField('id');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'id', 'hello', 'p_0');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_exact_condition_for_ulid_column_with_ulid_value(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'ulid');
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with('p_0', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'ulid');

        $column = TextColumn::new('id', 'ID')->setField('id');
        $result = SearchPredicateFactory::build(
            $qb,
            $column,
            'e',
            'id',
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'p_0',
        );

        $this->assertSame('e.id = :p_0', $result);
    }

    #[Test]
    public function it_binds_the_doctrine_type_for_binary_uuid_column(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'uuid_binary');
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('p_0', '018f2c3e-1234-7abc-9def-0123456789ab', 'uuid_binary');

        $column = TextColumn::new('id', 'ID')->setField('id');
        $result = SearchPredicateFactory::build(
            $qb,
            $column,
            'e',
            'id',
            '018f2c3e-1234-7abc-9def-0123456789ab',
            'p_0',
        );

        $this->assertSame('e.id = :p_0', $result);
    }

    #[Test]
    public function it_binds_a_trimmed_identifier_for_uuid_column(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'uuid');
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('p_0', '018f2c3e-1234-7abc-9def-0123456789ab', 'uuid');

        $column = TextColumn::new('id', 'ID')->setField('id');
        $result = SearchPredicateFactory::build(
            $qb,
            $column,
            'e',
            'id',
            '  018f2c3e-1234-7abc-9def-0123456789ab  ',
            'p_0',
        );

        $this->assertSame('e.id = :p_0', $result);
    }

    #[Test]
    public function it_returns_null_for_uuid_column_with_unhyphenated_value(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'uuid');
        $qb->expects($this->never())->method('setParameter');

        $column = TextColumn::new('id', 'ID')->setField('id');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'id', '018f2c3e12347abc9def0123456789ab', 'p_0');

        $this->assertNull($result);
    }
}
