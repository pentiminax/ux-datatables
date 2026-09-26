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

class DummyStringableEntity implements \Stringable
{
    public function __construct(
        private readonly int $id,
        private readonly string $title,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return 'Stringable: '.$this->title;
    }
}

class DummyNamedEntity
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

class DummyDisplayableEntity implements \Stringable
{
    public function __construct(
        private readonly int $id,
        private readonly string $display,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDisplay(): string
    {
        return $this->display;
    }

    public function __toString(): string
    {
        return 'Stringable: '.$this->display;
    }
}

/**
 * @internal
 */
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
    public function it_skips_entities_when_value_property_is_null_without_falling_back_to_id(): void
    {
        $entities = [
            new DummyEntity(1, 'Bachelor'),
            new DummyEntity(2, ''),
        ];

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($entities);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        // When requesting non-existent or null property 'code', it should not fallback to id 1 or 2
        $filter = ChoiceFilter::new('typeDiplome')->entity(
            class: DummyEntity::class,
            value: 'nonExistentCode',
        );

        $filters = (new Filters())->add($filter);
        $table   = (new DataTable('test_table'))->setFilters($filters);

        $resolver = new FilterEntityOptionsResolver($doctrine);
        $resolver->prepare($table);

        $this->assertSame([], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_auto_resolves_label_from_stringable_entity(): void
    {
        $entities = [
            new DummyStringableEntity(100, 'Article 1'),
            new DummyStringableEntity(101, 'Article 2'),
        ];

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($entities);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $filter = ChoiceFilter::new('article')->entity(DummyStringableEntity::class);

        $filters = (new Filters())->add($filter);
        $table   = (new DataTable('test_table'))->setFilters($filters);

        $resolver = new FilterEntityOptionsResolver($doctrine);
        $resolver->prepare($table);

        $this->assertSame([
            '100' => 'Stringable: Article 1',
            '101' => 'Stringable: Article 2',
        ], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_prefers_display_property_over_to_string_when_label_is_omitted(): void
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn([new DummyDisplayableEntity(300, 'Displayed')]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $filter = ChoiceFilter::new('level')->entity(DummyDisplayableEntity::class);
        $table  = (new DataTable('test_table'))->setFilters((new Filters())->add($filter));

        (new FilterEntityOptionsResolver($doctrine))->prepare($table);

        $this->assertSame(['300' => 'Displayed'], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_reads_a_string_label_as_a_property_path_even_if_it_names_a_php_function(): void
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn([new DummyEntity(1, 'Bachelor')]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $filter = ChoiceFilter::new('typeDiplome')->entity(DummyEntity::class, label: 'trim');
        $table  = (new DataTable('test_table'))->setFilters((new Filters())->add($filter));

        (new FilterEntityOptionsResolver($doctrine))->prepare($table);

        $this->assertSame(['1' => '1'], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_auto_resolves_label_from_name_property(): void
    {
        $entities = [
            new DummyNamedEntity(200, 'First Name'),
        ];

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($entities);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $filter = ChoiceFilter::new('user')->entity(DummyNamedEntity::class);

        $filters = (new Filters())->add($filter);
        $table   = (new DataTable('test_table'))->setFilters($filters);

        $resolver = new FilterEntityOptionsResolver($doctrine);
        $resolver->prepare($table);

        $this->assertSame([
            '200' => 'First Name',
        ], $filter->jsonSerialize()['options']);
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
