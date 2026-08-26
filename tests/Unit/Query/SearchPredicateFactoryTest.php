<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Query\SearchPredicateFactory;
use Pentiminax\UX\DataTables\Tests\Support\BuildsTypedFieldQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchPredicateFactory::class)]
final class SearchPredicateFactoryTest extends TestCase
{
    use BuildsTypedFieldQueryBuilder;

    private const string UUID = '018f2c3e-1234-7abc-9def-0123456789ab';

    private const string ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    /**
     * Each case gives the configured column, the Doctrine type of its field, the searched
     * value, whether numeric handling is forced, the expected DQL fragment and the exact
     * setParameter() arguments, or null when nothing may be bound.
     *
     * @return iterable<string, array{ColumnInterface, ?string, string, bool, ?string, ?array<int, mixed>}>
     */
    public static function searchPredicates(): iterable
    {
        yield 'text column' => [
            TextColumn::new('name', 'Name')->setField('name'),
            null,
            'hello',
            false,
            "e.name LIKE :p_0 ESCAPE '!'",
            ['p_0', '%hello%'],
        ];

        yield 'text column with like wildcards is escaped, not interpreted' => [
            TextColumn::new('name', 'Name')->setField('name'),
            null,
            '50%_off',
            false,
            "e.name LIKE :p_0 ESCAPE '!'",
            ['p_0', '%50!%!_off%'],
        ];

        yield 'numeric column with numeric value' => [
            NumberColumn::new('id', 'ID')->setField('id'),
            null,
            '42',
            false,
            'e.id = :p_0',
            ['p_0', '42', null],
        ];

        yield 'numeric column with non-numeric value' => [
            NumberColumn::new('id', 'ID')->setField('id'),
            null,
            'abc',
            false,
            null,
            null,
        ];

        yield 'non-numeric column forced to numeric' => [
            TextColumn::new('score', 'Score')->setField('score'),
            null,
            '42',
            true,
            'e.score = :p_0',
            ['p_0', '42', null],
        ];

        yield 'non-numeric value forced to numeric' => [
            TextColumn::new('score', 'Score')->setField('score'),
            null,
            'abc',
            true,
            null,
            null,
        ];

        yield 'non-text field' => [
            TextColumn::new('active', 'Active')->setField('active'),
            'boolean',
            'true',
            false,
            null,
            null,
        ];

        yield 'guid field with uuid value' => [
            TextColumn::new('id', 'ID')->setField('id'),
            'guid',
            self::UUID,
            false,
            'e.id = :p_0',
            ['p_0', self::UUID, 'guid'],
        ];

        yield 'guid field with partial text value' => [
            TextColumn::new('id', 'ID')->setField('id'),
            'guid',
            'hello',
            false,
            null,
            null,
        ];

        yield 'guid field with ulid value' => [
            TextColumn::new('id', 'ID')->setField('id'),
            'guid',
            self::ULID,
            false,
            null,
            null,
        ];

        yield 'ulid field with ulid value' => [
            TextColumn::new('id', 'ID')->setField('id'),
            'ulid',
            self::ULID,
            false,
            'e.id = :p_0',
            ['p_0', self::ULID, 'ulid'],
        ];

        yield 'ulid field with uuid value' => [
            TextColumn::new('id', 'ID')->setField('id'),
            'ulid',
            self::UUID,
            false,
            null,
            null,
        ];

        yield 'binary uuid field with uuid value' => [
            TextColumn::new('id', 'ID')->setField('id'),
            'uuid_binary',
            self::UUID,
            false,
            'e.id = :p_0',
            ['p_0', self::UUID, 'uuid_binary'],
        ];

        yield 'uuid field with padded value' => [
            TextColumn::new('id', 'ID')->setField('id'),
            'uuid',
            '  '.self::UUID.'  ',
            false,
            'e.id = :p_0',
            ['p_0', self::UUID, 'uuid'],
        ];

        yield 'uuid field with unhyphenated value' => [
            TextColumn::new('id', 'ID')->setField('id'),
            'uuid',
            '018f2c3e12347abc9def0123456789ab',
            false,
            null,
            null,
        ];

        yield 'integer field with integer value' => [
            NumberColumn::new('age', 'Age')->setField('age'),
            'integer',
            '42',
            false,
            'e.age = :p_0',
            ['p_0', '42', 'integer'],
        ];

        yield 'integer field with padded integer value' => [
            NumberColumn::new('age', 'Age')->setField('age'),
            'integer',
            '  42  ',
            false,
            'e.age = :p_0',
            ['p_0', '42', 'integer'],
        ];

        yield 'integer field with decimal value' => [
            NumberColumn::new('age', 'Age')->setField('age'),
            'integer',
            '1.5',
            false,
            null,
            null,
        ];

        yield 'integer field with scientific notation' => [
            NumberColumn::new('age', 'Age')->setField('age'),
            'integer',
            '1e2',
            false,
            null,
            null,
        ];

        yield 'integer field with non-numeric value' => [
            NumberColumn::new('age', 'Age')->setField('age'),
            'integer',
            'abc',
            false,
            null,
            null,
        ];

        yield 'forced numeric on integer field with decimal value' => [
            TextColumn::new('age', 'Age')->setField('age'),
            'integer',
            '1.5',
            true,
            null,
            null,
        ];

        yield 'float field with decimal value' => [
            NumberColumn::new('price', 'Price')->setField('price'),
            'float',
            '19.99',
            false,
            'e.price = :p_0',
            ['p_0', '19.99', 'float'],
        ];

        yield 'float field with non-numeric value' => [
            NumberColumn::new('price', 'Price')->setField('price'),
            'float',
            'abc',
            false,
            null,
            null,
        ];
    }

    /**
     * @param ?array<int, mixed> $expectedParameter
     */
    #[Test]
    #[DataProvider('searchPredicates')]
    public function it_builds_the_search_predicate(
        ColumnInterface $column,
        ?string $fieldType,
        string $value,
        bool $forceNumeric,
        ?string $expectedCondition,
        ?array $expectedParameter,
    ): void {
        $field = (string) $column->getField();
        $qb    = $this->queryBuilderWithFieldType($field, $fieldType);

        if (null === $expectedParameter) {
            $qb->expects($this->never())->method('setParameter');
        } else {
            $qb->expects($this->once())->method('setParameter')->with(...$expectedParameter);
        }

        $result = SearchPredicateFactory::build($qb, $column, 'e', $field, $value, 'p_0', $forceNumeric);

        $this->assertSame($expectedCondition, $result);
    }

    #[Test]
    public function it_returns_null_for_text_column_with_association_field(): void
    {
        $qb = $this->queryBuilderWithAssociationField('client');
        $qb->expects($this->never())->method('setParameter');

        $column = TextColumn::new('client', 'Client')->setField('client');
        $result = SearchPredicateFactory::build($qb, $column, 'e', 'client', 'acme', 'p_0');

        $this->assertNull($result);
    }
}
