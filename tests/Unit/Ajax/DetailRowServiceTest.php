<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\DetailRowService;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
            $this->locatorReturning($entity),
            new Environment(new ArrayLoader(['detail.html.twig' => 'Email: {{ entity.email }} / {{ extra }}'])),
        );

        $result = $service->handleView($this->resolved(new CollapsibleDetailDataTable()), 7);

        $this->assertTrue($result->success);
        $this->assertSame('Email: alice@example.com / hint', $result->html);
    }

    #[Test]
    public function it_returns_forbidden_without_rendering_when_view_is_not_granted(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('VIEW', $this->anything())->willReturn(false);

        $service = $this->createService(
            $this->locatorReturning(new DetailRowEntity('alice@example.com')),
            $this->twigThatNeverRenders(),
            new PermissionChecker($checker),
        );

        $result = $service->handleView($this->resolved(new CollapsibleDetailDataTable()), 7);

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_enforces_the_permission_configured_on_the_detail_action(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects($this->once())->method('isGranted')->with('SHOW_DETAIL', null)->willReturn(false);

        $service = $this->createService(
            $this->locatorReturning(new DetailRowEntity('alice@example.com')),
            $this->twigThatNeverRenders(),
            new PermissionChecker($checker),
        );

        $result = $service->handleView($this->resolved(new StaticPermissionDetailDataTable()), 7);

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
    }

    #[Test]
    public function it_passes_the_located_entity_to_a_per_row_permission_resolver(): void
    {
        $entity = new DetailRowEntity('alice@example.com');

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects($this->once())->method('isGranted')->with('SHOW_DETAIL', 'alice@example.com')->willReturn(true);

        $service = $this->createService(
            $this->locatorReturning($entity),
            new Environment(new ArrayLoader(['detail.html.twig' => 'Email: {{ entity.email }}'])),
            new PermissionChecker($checker),
        );

        $result = $service->handleView($this->resolved(new PerRowPermissionDetailDataTable()), 7);

        $this->assertTrue($result->success);
        $this->assertSame('Email: alice@example.com', $result->html);
    }

    #[Test]
    public function it_returns_bad_request_when_no_collapsible_detail_action_is_configured(): void
    {
        $service = $this->createService(new EntityLocator(null), $this->twigThatNeverRenders());

        $result = $service->handleView($this->resolved(new PlainDetailDataTable()), 7);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_returns_not_found_when_the_entity_is_missing(): void
    {
        // No doctrine registry => the locator always throws EntityNotFoundException.
        $service = $this->createService(new EntityLocator(null), $this->twigThatNeverRenders());

        $result = $service->handleView($this->resolved(new CollapsibleDetailDataTable()), 404);

        $this->assertFalse($result->success);
        $this->assertSame(404, $result->statusCode);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_returns_bad_request_when_twig_is_unavailable(): void
    {
        $service = $this->createService(new EntityLocator(null), null);

        $result = $service->handleView($this->resolved(new CollapsibleDetailDataTable()), 7);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertNull($result->html);
    }

    private function createService(
        EntityLocator $locator,
        ?Environment $twig,
        ?PermissionChecker $permissionChecker = null,
    ): DetailRowService {
        return new DetailRowService($locator, $twig, $permissionChecker ?? new PermissionChecker());
    }

    private function resolved(AbstractDataTable $dataTable): ResolvedDataTable
    {
        return new ResolvedDataTable($dataTable, DetailRowEntity::class, $dataTable::class);
    }

    private function twigThatNeverRenders(): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        return $twig;
    }

    private function locatorReturning(object $entity): EntityLocator
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('find')->willReturn($entity);

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);

        $registry = $this->createStub(ManagerRegistry::class);
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

#[AsDataTable(entityClass: DetailRowEntity::class)]
final class StaticPermissionDetailDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('email', 'Email');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail()->collapsible('detail.html.twig')->permission('SHOW_DETAIL')
        );
    }
}

#[AsDataTable(entityClass: DetailRowEntity::class)]
final class PerRowPermissionDetailDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('email', 'Email');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail()
                ->collapsible('detail.html.twig')
                ->permission('SHOW_DETAIL', static fn (DetailRowEntity $entity): string => $entity->email)
        );
    }
}
