<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Detail;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Detail\DetailRowService;
use Pentiminax\UX\DataTables\Dto\AjaxDetailQueryDto;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(DetailRowService::class)]
final class DetailRowServiceTest extends TestCase
{
    #[Test]
    public function it_renders_the_collapsible_detail_template_with_the_entity(): void
    {
        $entity = new DetailRowEntity('alice@example.com');

        $service = $this->createService(
            [CollapsibleDetailDataTable::class => new CollapsibleDetailDataTable()],
            $this->locatorReturning($entity),
            new Environment(new ArrayLoader(['detail.html.twig' => 'Email: {{ entity.email }} / {{ extra }}'])),
        );

        $result = $service->handleView(new AjaxDetailQueryDto(
            entity: DetailRowEntity::class,
            id: 7,
            dataTableClass: CollapsibleDetailDataTable::class,
        ));

        $this->assertTrue($result->success);
        $this->assertSame('Email: alice@example.com / hint', $result->html);
    }

    #[Test]
    public function it_returns_bad_request_without_a_data_table_class(): void
    {
        $service = $this->createService([], new EntityLocator(null), $this->createMock(Environment::class));

        $result = $service->handleView(new AjaxDetailQueryDto(DetailRowEntity::class, 7, null));

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
    }

    #[Test]
    public function it_returns_bad_request_when_no_collapsible_detail_action_is_configured(): void
    {
        $service = $this->createService(
            [PlainDetailDataTable::class => new PlainDetailDataTable()],
            new EntityLocator(null),
            $this->createMock(Environment::class),
        );

        $result = $service->handleView(new AjaxDetailQueryDto(
            DetailRowEntity::class,
            7,
            PlainDetailDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
    }

    #[Test]
    public function it_returns_not_found_when_the_entity_is_missing(): void
    {
        $service = $this->createService(
            [CollapsibleDetailDataTable::class => new CollapsibleDetailDataTable()],
            new EntityLocator(null), // no doctrine => always throws EntityNotFoundException
            new Environment(new ArrayLoader(['detail.html.twig' => 'x'])),
        );

        $result = $service->handleView(new AjaxDetailQueryDto(
            DetailRowEntity::class,
            404,
            CollapsibleDetailDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(404, $result->statusCode);
    }

    #[Test]
    public function it_returns_bad_request_when_twig_is_unavailable(): void
    {
        $service = new DetailRowService(
            new DetailTestContainer([CollapsibleDetailDataTable::class => new CollapsibleDetailDataTable()]),
            new EntityLocator(null),
            null,
        );

        $result = $service->handleView(new AjaxDetailQueryDto(
            DetailRowEntity::class,
            7,
            CollapsibleDetailDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
    }

    /**
     * @param array<string, AbstractDataTable> $dataTables
     */
    private function createService(array $dataTables, EntityLocator $locator, Environment $twig): DetailRowService
    {
        return new DetailRowService(new DetailTestContainer($dataTables), $locator, $twig);
    }

    private function locatorReturning(object $entity): EntityLocator
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return new EntityLocator($registry);
    }
}

final class DetailRowEntity
{
    public function __construct(public readonly string $email)
    {
    }
}

#[AsDataTable(entityClass: DetailRowEntity::class)]
final class CollapsibleDetailDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('email', 'Email');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::detail()->collapsible('detail.html.twig', ['extra' => 'hint']));
    }
}

#[AsDataTable(entityClass: DetailRowEntity::class)]
final class PlainDetailDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('email', 'Email');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::detail()->linkToUrl('/detail/1'));
    }
}

/**
 * @implements ContainerInterface<object>
 */
final readonly class DetailTestContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(private array $services)
    {
    }

    public function get(string $id): object
    {
        if (!$this->has($id)) {
            throw new \RuntimeException(\sprintf('Unknown service "%s".', $id));
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
