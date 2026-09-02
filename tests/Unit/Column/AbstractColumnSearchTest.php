<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractColumn::class)]
final class AbstractColumnSearchTest extends TestCase
{
    #[Test]
    public function it_has_no_search_configuration_by_default(): void
    {
        $column = TextColumn::new('name', 'Name');

        $this->assertNull($column->getSearchField());
        $this->assertSame([], $column->getSearchJoins());
        $this->assertNull($column->buildSearchPredicate($this->createMock(QueryBuilder::class), 'e', 'ali', 'p0'));
    }

    #[Test]
    public function it_keeps_the_search_field_separate_from_the_display_field(): void
    {
        $column = TextColumn::new('donorProviderName', 'Donor')
            ->setField('donorProviderName')
            ->setSearchField('donorProvider.name');

        $this->assertSame('donorProviderName', $column->getField());
        $this->assertSame('donorProvider.name', $column->getSearchField());
    }

    #[Test]
    public function it_leaves_the_search_field_out_of_the_client_payload(): void
    {
        $payload = TextColumn::new('donorProviderName', 'Donor')
            ->setSearchField('donorProvider.name')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->jsonSerialize();

        $this->assertSame('donorProviderName', $payload['field']);
        $this->assertArrayNotHasKey('searchField', $payload);
        $this->assertArrayNotHasKey('searchJoins', $payload);
    }

    #[Test]
    public function it_appends_search_joins_in_declaration_order(): void
    {
        $column = TextColumn::new('city', 'City')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->addSearchJoin('dp.address', 'dpa', 'WITH', 'dpa.primary = true');

        $this->assertSame([
            ['join' => 'e.donorProvider', 'alias' => 'dp', 'conditionType' => null, 'condition' => null],
            ['join' => 'dp.address', 'alias' => 'dpa', 'conditionType' => 'WITH', 'condition' => 'dpa.primary = true'],
        ], $column->getSearchJoins());
    }

    #[Test]
    public function it_delegates_to_the_configured_search_predicate(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('setParameter')->with('p0', '%acme%')->willReturn($qb);

        $column = TextColumn::new('donorProviderName', 'Donor')->setSearchPredicate(
            static function (QueryBuilder $qb, string $alias, string $value, string $paramName): string {
                $qb->setParameter($paramName, \sprintf('%%%s%%', $value));

                return \sprintf('%s.name LIKE :%s', $alias, $paramName);
            }
        );

        $this->assertSame('e.name LIKE :p0', $column->buildSearchPredicate($qb, 'e', 'acme', 'p0'));
    }

    #[Test]
    public function it_returns_null_when_the_search_predicate_declines_the_value(): void
    {
        $column = TextColumn::new('dossierId', 'Dossier')
            ->setSearchPredicate(static fn (): ?string => null);

        $this->assertNull($column->buildSearchPredicate($this->createMock(QueryBuilder::class), 'e', 'x', 'p0'));
    }

    #[Test]
    public function it_replaces_a_previously_configured_search_predicate(): void
    {
        $column = TextColumn::new('name', 'Name')
            ->setSearchPredicate(static fn (): string => 'first')
            ->setSearchPredicate(static fn (): string => 'second');

        $this->assertSame('second', $column->buildSearchPredicate($this->createMock(QueryBuilder::class), 'e', 'x', 'p0'));
    }
}
