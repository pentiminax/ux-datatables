<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Tests\Support\BuildsTypedFieldQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DefaultSearchPredicateBuilder is a thin SearchPredicateBuilderInterface wrapper around
 * SearchPredicateFactory::build(); the type-dispatch behavior itself is covered by
 * SearchPredicateFactoryTest, so this only asserts the delegation.
 *
 * @internal
 */
#[CoversClass(DefaultSearchPredicateBuilder::class)]
final class DefaultSearchPredicateBuilderTest extends TestCase
{
    use BuildsTypedFieldQueryBuilder;

    #[Test]
    public function it_delegates_to_search_predicate_factory(): void
    {
        $qb = $this->queryBuilderWithFieldType('name', null);
        $qb->expects($this->once())->method('setParameter')->with('p_0', '%hello%');

        $result = (new DefaultSearchPredicateBuilder())->build(
            $qb,
            TextColumn::new('name', 'Name')->setField('name'),
            'e',
            'name',
            'hello',
            'p_0',
        );

        $this->assertSame("e.name LIKE :p_0 ESCAPE '!'", $result);
    }

    #[Test]
    public function it_forwards_force_numeric(): void
    {
        $qb = $this->queryBuilderWithFieldType('score', null);
        $qb->expects($this->once())->method('setParameter')->with('p_0', '42', null);

        $result = (new DefaultSearchPredicateBuilder())->build(
            $qb,
            TextColumn::new('score', 'Score')->setField('score'),
            'e',
            'score',
            '42',
            'p_0',
            forceNumeric: true,
        );

        $this->assertSame('e.score = :p_0', $result);
    }

    #[Test]
    public function it_returns_null_when_the_factory_rejects_the_value(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', null);
        $qb->expects($this->never())->method('setParameter');

        $result = (new DefaultSearchPredicateBuilder())->build(
            $qb,
            NumberColumn::new('id', 'ID')->setField('id'),
            'e',
            'id',
            'not-a-number',
            'p_0',
        );

        $this->assertNull($result);
    }
}
