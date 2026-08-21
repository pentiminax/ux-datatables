<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractColumn::class)]
final class AbstractColumnSearchTest extends TestCase
{
    // -----------------------------------------------------------------------
    // setSearchField / getSearchField
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_null_search_field_by_default(): void
    {
        $column = TextColumn::new('name');

        $this->assertNull($column->getSearchField());
    }

    #[Test]
    public function set_search_field_is_fluent_and_stored(): void
    {
        $column = TextColumn::new('donorProviderName');

        $result = $column->setSearchField('donorProvider.name');

        $this->assertSame($column, $result);
        $this->assertSame('donorProvider.name', $column->getSearchField());
    }

    #[Test]
    public function search_field_is_not_serialized_to_client(): void
    {
        $column = TextColumn::new('donorProviderName')->setSearchField('donorProvider.name');

        $this->assertArrayNotHasKey('searchField', $column->jsonSerialize());
    }

    #[Test]
    public function set_search_field_does_not_affect_get_field(): void
    {
        $column = TextColumn::new('donorProviderName')
            ->setField('donorProviderName')
            ->setSearchField('donorProvider.name');

        $this->assertSame('donorProviderName', $column->getField());
        $this->assertSame('donorProvider.name', $column->getSearchField());
    }

    // -----------------------------------------------------------------------
    // addSearchJoin / getSearchJoins
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_empty_search_joins_by_default(): void
    {
        $column = TextColumn::new('name');

        $this->assertSame([], $column->getSearchJoins());
    }

    #[Test]
    public function add_search_join_is_fluent_and_stored(): void
    {
        $column = TextColumn::new('donorProviderName');

        $result = $column->addSearchJoin('e.donorProvider', 'dp');

        $this->assertSame($column, $result);
        $this->assertSame([
            ['join' => 'e.donorProvider', 'alias' => 'dp', 'conditionType' => null, 'condition' => null],
        ], $column->getSearchJoins());
    }

    #[Test]
    public function multiple_search_joins_are_accumulated(): void
    {
        $column = TextColumn::new('name')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->addSearchJoin('dp.address', 'dp_addr', 'WITH', 'dp_addr.active = true');

        $this->assertSame([
            ['join' => 'e.donorProvider', 'alias' => 'dp', 'conditionType' => null, 'condition' => null],
            ['join' => 'dp.address', 'alias' => 'dp_addr', 'conditionType' => 'WITH', 'condition' => 'dp_addr.active = true'],
        ], $column->getSearchJoins());
    }

    #[Test]
    public function search_joins_are_not_serialized_to_client(): void
    {
        $column = TextColumn::new('name')->addSearchJoin('e.donorProvider', 'dp');

        $this->assertArrayNotHasKey('searchJoins', $column->jsonSerialize());
    }

    // -----------------------------------------------------------------------
    // setSearchPredicate / buildSearchPredicate / SearchableColumnInterface
    // -----------------------------------------------------------------------

    #[Test]
    public function abstract_column_implements_searchable_column_interface(): void
    {
        $column = TextColumn::new('name');

        $this->assertInstanceOf(SearchableColumnInterface::class, $column);
    }

    #[Test]
    public function build_search_predicate_returns_null_when_no_closure_set(): void
    {
        $column = TextColumn::new('name');
        $qb     = $this->createMock(QueryBuilder::class);

        $this->assertNull($column->buildSearchPredicate($qb, 'e', 'foo', 'param_0'));
    }

    #[Test]
    public function set_search_predicate_is_fluent(): void
    {
        $column = TextColumn::new('name');

        $result = $column->setSearchPredicate(static fn () => null);

        $this->assertSame($column, $result);
    }

    #[Test]
    public function set_search_predicate_closure_is_invoked_by_build_search_predicate(): void
    {
        $column = TextColumn::new('donorProviderName');

        $captured = [];
        $column->setSearchPredicate(
            function (QueryBuilder $qb, string $alias, string $value, string $paramName) use (&$captured): string {
                $captured = [$alias, $value, $paramName];
                $qb->setParameter($paramName, '%'.$value.'%');

                return "dp.name LIKE :{$paramName}";
            }
        );

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('p0', '%acme%');

        $result = $column->buildSearchPredicate($qb, 'e', 'acme', 'p0');

        $this->assertSame('dp.name LIKE :p0', $result);
        $this->assertSame(['e', 'acme', 'p0'], $captured);
    }

    #[Test]
    public function set_search_predicate_closure_returning_null_propagates_null(): void
    {
        $column = TextColumn::new('name');
        $column->setSearchPredicate(static fn () => null);

        $qb = $this->createMock(QueryBuilder::class);

        $this->assertNull($column->buildSearchPredicate($qb, 'e', 'foo', 'p0'));
    }

    #[Test]
    public function search_predicate_is_not_serialized_to_client(): void
    {
        $column = TextColumn::new('name')
            ->setSearchPredicate(static fn () => 'name LIKE :p');

        $serialized = $column->jsonSerialize();

        $this->assertArrayNotHasKey('searchPredicate', $serialized);
    }

    #[Test]
    public function subclass_can_override_build_search_predicate_directly(): void
    {
        $column = new class extends AbstractColumn {
            public function __construct()
            {
                $this->type = ColumnType::STRING;
                $this->setName('name');
            }

            public function buildSearchPredicate(
                QueryBuilder $qb,
                string $alias,
                string $value,
                string $paramName,
            ): ?string {
                $qb->setParameter($paramName, $value);

                return \sprintf('%s.customField = :%s', $alias, $paramName);
            }
        };

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('setParameter')->with('p0', 'test');

        $result = $column->buildSearchPredicate($qb, 'e', 'test', 'p0');

        $this->assertSame('e.customField = :p0', $result);
    }
}
