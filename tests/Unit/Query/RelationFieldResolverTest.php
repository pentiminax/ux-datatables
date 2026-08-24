<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Tests\Support\BuildsTypedFieldQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RelationFieldResolver::class)]
final class RelationFieldResolverTest extends TestCase
{
    use BuildsTypedFieldQueryBuilder;

    /**
     * Each case gives the aliases already joined on the query builder, the resolved field
     * path, the expected DQL expression and the expected left join calls in order.
     *
     * @return iterable<string, array{list<string>, string, string, list<array{string, string}>}>
     */
    public static function resolvedFieldPaths(): iterable
    {
        yield 'simple field' => [[], 'name', 'e.name', []];

        yield 'single level relation' => [
            [],
            'author.firstName',
            'author.firstName',
            [['e.author', 'author']],
        ];

        yield 'multi level relation' => [
            [],
            'author.address.city',
            'author_address.city',
            [['e.author', 'author'], ['author.address', 'author_address']],
        ];

        yield 'already joined relation' => [['author'], 'author.firstName', 'author.firstName', []];

        yield 'partially joined relation' => [
            ['author'],
            'author.address.city',
            'author_address.city',
            [['author.address', 'author_address']],
        ];
    }

    /**
     * Each case gives the Doctrine type of the field, whether it supports SQL LIKE, the
     * UUID type it must be bound with, and the date type it must be bound with. A null type
     * stands for unavailable root metadata.
     *
     * @return iterable<string, array{?string, bool, ?string, ?string}>
     */
    public static function fieldTypes(): iterable
    {
        yield 'unavailable root entity metadata' => [null, true, null, null];

        yield 'string' => ['string', true, null, null];

        yield 'boolean' => ['boolean', false, null, null];

        yield 'guid' => ['guid', false, 'guid', null];

        yield 'uuid' => ['uuid', false, 'uuid', null];

        yield 'ulid' => ['ulid', false, 'ulid', null];

        yield 'binary uuid' => ['uuid_binary_ordered_time', false, 'uuid_binary_ordered_time', null];

        yield 'date' => ['date', false, null, 'date'];

        yield 'datetime_immutable' => ['datetime_immutable', false, null, 'datetime_immutable'];
    }

    /**
     * @param list<string>                $existingJoinAliases
     * @param list<array{string, string}> $expectedJoins
     */
    #[Test]
    #[DataProvider('resolvedFieldPaths')]
    public function it_resolves_field_paths_and_joins_missing_relations(
        array $existingJoinAliases,
        string $fieldPath,
        string $expectedExpression,
        array $expectedJoins,
    ): void {
        $qb = $this->queryBuilderWithJoinAliases($existingJoinAliases);

        $joinCalls = [];

        if ([] === $expectedJoins) {
            $qb->expects($this->never())->method('leftJoin');
        } else {
            $qb->expects($this->exactly(\count($expectedJoins)))
                ->method('leftJoin')
                ->willReturnCallback(function (string $join, string $alias) use ($qb, &$joinCalls) {
                    $joinCalls[] = [$join, $alias];

                    return $qb;
                });
        }

        $result = RelationFieldResolver::resolve($qb, 'e', $fieldPath);

        $this->assertSame($expectedExpression, $result);
        $this->assertSame($expectedJoins, $joinCalls);
    }

    #[Test]
    #[DataProvider('fieldTypes')]
    public function it_classifies_a_scalar_field_by_doctrine_type(
        ?string $fieldType,
        bool $supportsTextSearch,
        ?string $expectedUuidType,
        ?string $expectedDateType,
    ): void {
        $qb = $this->queryBuilderWithFieldType('id', $fieldType);

        $this->assertTrue(RelationFieldResolver::supportsSearchFiltering($qb, 'id'));
        $this->assertSame($supportsTextSearch, RelationFieldResolver::supportsTextSearch($qb, 'id'));
        $this->assertSame($expectedUuidType, RelationFieldResolver::resolveUuidFieldType($qb, 'id'));
        $this->assertSame($expectedDateType, RelationFieldResolver::resolveDateFieldType($qb, 'id'));
    }

    #[Test]
    #[DataProvider('numeric_and_boolean_field_types')]
    public function it_classifies_numeric_and_boolean_doctrine_types(
        string $fieldType,
        ?string $expectedIntegerType,
        ?string $expectedFloatType,
        ?string $expectedBooleanType,
    ): void {
        $qb = $this->queryBuilderWithFieldType('id', $fieldType);

        $this->assertSame($expectedIntegerType, RelationFieldResolver::resolveIntegerFieldType($qb, 'id'));
        $this->assertSame($expectedFloatType, RelationFieldResolver::resolveFloatFieldType($qb, 'id'));
        $this->assertSame($expectedBooleanType, RelationFieldResolver::resolveBooleanFieldType($qb, 'id'));
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, ?string}>
     */
    public static function numeric_and_boolean_field_types(): iterable
    {
        yield 'integer' => ['integer', 'integer', null, null];
        yield 'smallint' => ['smallint', 'smallint', null, null];
        yield 'bigint' => ['bigint', 'bigint', null, null];
        yield 'float' => ['float', null, 'float', null];
        yield 'decimal' => ['decimal', null, 'decimal', null];
        yield 'boolean' => ['boolean', null, null, 'boolean'];
        yield 'string' => ['string', null, null, null];
        yield 'date' => ['date', null, null, null];
    }

    #[Test]
    public function it_rejects_a_bare_association_field(): void
    {
        $qb = $this->queryBuilderWithAssociationField('client');

        $this->assertFalse(RelationFieldResolver::supportsSearchFiltering($qb, 'client'));
        $this->assertFalse(RelationFieldResolver::supportsTextSearch($qb, 'client'));
        $this->assertNull(RelationFieldResolver::resolveUuidFieldType($qb, 'client'));
        $this->assertNull(RelationFieldResolver::resolveDateFieldType($qb, 'client'));
        $this->assertNull(RelationFieldResolver::resolveIntegerFieldType($qb, 'client'));
        $this->assertNull(RelationFieldResolver::resolveFloatFieldType($qb, 'client'));
        $this->assertNull(RelationFieldResolver::resolveBooleanFieldType($qb, 'client'));
    }

    #[Test]
    public function it_supports_search_filtering_for_a_dot_notation_path_without_reading_metadata(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('getRootEntities');

        $this->assertTrue(RelationFieldResolver::supportsSearchFiltering($qb, 'client.name'));
    }

    /**
     * @param list<string> $aliases
     */
    private function queryBuilderWithJoinAliases(array $aliases): MockObject&QueryBuilder
    {
        $joins = [];

        foreach ($aliases as $alias) {
            $join = $this->createMock(Join::class);
            $join->method('getAlias')->willReturn($alias);

            $joins[] = $join;
        }

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([] === $joins ? [] : ['e' => $joins]);

        return $qb;
    }
}
