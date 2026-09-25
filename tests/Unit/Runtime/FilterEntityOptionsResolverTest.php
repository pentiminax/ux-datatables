<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Runtime\FilterEntityOptionsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DummyEntity
{
    public function __construct(
        private readonly int $id,
        private readonly string $libelle,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }
}

#[CoversClass(FilterEntityOptionsResolver::class)]
final class FilterEntityOptionsResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_options_from_entity_repository(): void
    {
        $entities = [
            new DummyEntity(1, 'Bachelor'),
            new DummyEntity(2, 'Master'),
        ];

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($entities);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $filter = ChoiceFilter::new('typeDiplome')->entity(DummyEntity::class);

        $filters = (new Filters())->add($filter);
        $table   = (new DataTable('test_table'))->setFilters($filters);

        $resolver = new FilterEntityOptionsResolver($doctrine);
        $resolver->prepare($table);

        $this->assertSame([
            '1' => 'Bachelor',
            '2' => 'Master',
        ], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_supports_custom_label_closure(): void
    {
        $entities = [
            new DummyEntity(10, 'Alpha'),
        ];

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($entities);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $filter = ChoiceFilter::new('typeDiplome')->entity(
            class: DummyEntity::class,
            label: static fn (DummyEntity $e): string => strtoupper($e->getLibelle()),
        );

        $filters = (new Filters())->add($filter);
        $table   = (new DataTable('test_table'))->setFilters($filters);

        $resolver = new FilterEntityOptionsResolver($doctrine);
        $resolver->prepare($table);

        $this->assertSame(['10' => 'ALPHA'], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_supports_custom_query_builder_closure(): void
    {
        $entities = [
            new DummyEntity(5, 'Custom'),
        ];

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($entities);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $invoked = false;
        $filter  = ChoiceFilter::new('typeDiplome')->entity(
            class: DummyEntity::class,
            queryBuilder: static function ($repo, $builder) use (&$invoked): void {
                $invoked = true;
            },
        );

        $filters = (new Filters())->add($filter);
        $table   = (new DataTable('test_table'))->setFilters($filters);

        $resolver = new FilterEntityOptionsResolver($doctrine);
        $resolver->prepare($table);

        $this->assertTrue($invoked);
        $this->assertSame(['5' => 'Custom'], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_throws_when_doctrine_is_missing(): void
    {
        $filter = ChoiceFilter::new('typeDiplome')->entity(DummyEntity::class);

        $filters = (new Filters())->add($filter);
        $table   = (new DataTable('test_table'))->setFilters($filters);

        $resolver = new FilterEntityOptionsResolver(null);

        $this->expectException(\LogicException::class);
        $resolver->prepare($table);
    }
}
