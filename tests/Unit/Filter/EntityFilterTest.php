<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Filter\EntityFilter;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Runtime\FilterEntityOptionsResolver;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountLine;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use Pentiminax\UX\DataTables\Tests\Support\BuildsFilterQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EntityFilter::class)]
#[CoversClass(FilterEntityOptionsResolver::class)]
final class EntityFilterTest extends TestCase
{
    use BuildsEntityManager;
    use BuildsFilterQueryBuilder;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountTag::class, CountLine::class);

        foreach ([1 => 'Bob', 2 => 'Alice', 3 => 'Carol'] as $id => $name) {
            $this->em->persist(new CountCustomer($id, $name));
        }
        $this->em->flush();

        foreach ([[1, 1], [2, 1], [3, 2], [4, 3], [5, null]] as [$id, $customerId]) {
            $tag           = new CountTag($id, 'tag'.$id);
            $tag->customer = null === $customerId ? null : $this->em->getReference(CountCustomer::class, $customerId);
            $this->em->persist($tag);
        }
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_serializes_as_a_select_with_the_resolved_options(): void
    {
        $filter = EntityFilter::new('customer')->entity(CountCustomer::class)->optionLabel('name')->label('Customer')->multiple();
        $this->resolve($filter);

        $this->assertSame([
            'name'     => 'customer',
            'type'     => 'select',
            'label'    => 'Customer',
            'options'  => ['2' => 'Alice', '1' => 'Bob', '3' => 'Carol'],
            'multiple' => true,
        ], $filter->jsonSerialize());
    }

    #[Test]
    public function it_applies_an_equality_on_the_association_with_a_bound_parameter(): void
    {
        $this->assertFilterProduces(
            EntityFilter::new('customer')->entity(CountCustomer::class),
            '2',
            ['e.customer = :filter_customer'],
            ['filter_customer' => '2'],
        );
    }

    #[Test]
    public function it_applies_in_for_several_values(): void
    {
        $this->assertFilterProduces(
            EntityFilter::new('customer')->entity(CountCustomer::class)->multiple(),
            ['1', '', '3', ['x'], null],
            ['e.customer IN (:filter_customer_in)'],
            ['filter_customer_in' => ['1', '3']],
        );
    }

    /**
     * @param list<int> $expectedIds
     */
    #[Test]
    #[DataProvider('provideDatabaseCases')]
    public function it_filters_rows_by_association_on_sqlite(bool $multiple, mixed $value, array $expectedIds): void
    {
        $qb = $this->em->createQueryBuilder()->select('e')->from(CountTag::class, 'e')->orderBy('e.id');
        EntityFilter::new('customer')->entity(CountCustomer::class)->multiple($multiple)->apply($qb, $value, 'e');

        $ids = array_map(static fn (CountTag $t): int => $t->id, $qb->getQuery()->getResult());

        $this->assertSame($expectedIds, $ids);
    }

    /**
     * @return iterable<string, array{bool, mixed, list<int>}>
     */
    public static function provideDatabaseCases(): iterable
    {
        yield 'single' => [false, '1', [1, 2]];
        yield 'multiple' => [true, ['2', '3'], [3, 4]];
        yield 'multiple given a scalar' => [true, '3', [4]];
        yield 'unknown identifier matches nothing' => [false, '99', []];
        yield 'empty string is ignored' => [false, '', [1, 2, 3, 4, 5]];
        yield 'array on a single filter is ignored' => [false, ['1'], [1, 2, 3, 4, 5]];
        yield 'injection attempt is only a value' => [false, '1 OR 1=1', []];
        yield 'empty multiple is ignored' => [true, [], [1, 2, 3, 4, 5]];
    }

    #[Test]
    public function it_honors_the_options_query_customizer(): void
    {
        $filter = EntityFilter::new('customer')
            ->entity(CountCustomer::class)
            ->optionLabel(static fn (CountCustomer $c): string => strtoupper($c->name))
            ->optionsQuery(static function (QueryBuilder $qb): void {
                $qb->andWhere('e.id != :hidden')->setParameter('hidden', 2)->orderBy('e.id', 'DESC');
            });

        $this->resolve($filter);

        $this->assertSame(['3' => 'CAROL', '1' => 'BOB'], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_accepts_a_list_exactly_at_the_limit(): void
    {
        $filter = EntityFilter::new('customer')->entity(CountCustomer::class)->limit(3);
        $this->resolve($filter);

        $this->assertCount(3, $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_fails_above_the_limit_with_a_clear_message(): void
    {
        $filter = EntityFilter::new('customer')->entity(CountCustomer::class)->limit(2);

        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('The entity filter "customer" lists more than 2 "'.CountCustomer::class.'" options. Narrow the list with optionsQuery() or raise limit().');

        $this->resolve($filter);
    }

    #[Test]
    public function it_defaults_the_limit_to_two_hundred(): void
    {
        $this->assertSame(200, EntityFilter::new('customer')->getLimit());
    }

    #[Test]
    public function it_rejects_a_limit_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 1.');

        EntityFilter::new('customer')->limit(0);
    }

    #[Test]
    public function it_requires_an_entity_class(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no entity class');

        $this->resolve(EntityFilter::new('customer'));
    }

    #[Test]
    public function it_rejects_a_composite_identifier(): void
    {
        $this->em->persist(new CountLine(1, 1, 'line'));
        $this->em->flush();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('needs a single-column identifier');

        $this->resolve(EntityFilter::new('line')->entity(CountLine::class));
    }

    private function resolve(EntityFilter $filter): void
    {
        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->em);

        $table = (new DataTable('test_table'))->setFilters((new Filters())->add($filter));

        (new FilterEntityOptionsResolver($doctrine))->prepare($table);
    }
}
