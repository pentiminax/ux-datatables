<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Ajax\AuthorizedRow;
use Pentiminax\UX\DataTables\Ajax\DetailRowService;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\MutationContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsUnscopedRowScopeGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(DetailRowService::class)]
final class DetailRowServiceTest extends TestCase
{
    use BuildsUnscopedRowScopeGuard;

    #[Test]
    public function it_renders_an_authorized_row(): void
    {
        $service = $this->createService(
            new EntityLocator(null),
            new Environment(new ArrayLoader(['detail.html.twig' => 'Email: {{ entity.email }} / {{ extra }}'])),
        );

        $result = $service->render($this->authorizedRow(new DetailRowEntity('alice@example.com')));

        $this->assertTrue($result->success);
        $this->assertSame('Email: alice@example.com / hint', $result->html);
    }

    #[Test]
    public function it_returns_bad_request_for_an_authorized_row_when_twig_is_unavailable(): void
    {
        $service = $this->createService(new EntityLocator(null), null);

        $result = $service->render($this->authorizedRow(new DetailRowEntity('alice@example.com')));

        $this->assertSame(400, $result->statusCode);
    }

    private function authorizedRow(DetailRowEntity $entity): AuthorizedRow
    {
        $table = $this->resolved(new CollapsibleDetailDataTable());

        return new AuthorizedRow(
            table: $table,
            action: $table->findAction(ActionType::Detail, collapsible: true),
            context: new MutationContext($entity, $this->createStub(ObjectManager::class)),
            id: 7,
        );
    }

    private function createService(
        EntityLocator $locator,
        ?Environment $twig,
        ?AuthorizationChecker $permissionChecker = null,
    ): DetailRowService {
        return new DetailRowService($locator, $this->unscopedRowScopeGuard(), $twig, $permissionChecker ?? new AuthorizationChecker(new TestAuthorizationChecker()));
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
