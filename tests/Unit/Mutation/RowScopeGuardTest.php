<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Ajax\DetailRowService;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Controller\AjaxDeleteController;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Controller\AjaxEditRequestDto;
use Pentiminax\UX\DataTables\Controller\AjaxEntityQueryDto;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalTemplateResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountAccount;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Account 3 is hidden: the table scope excludes it, yet it exists and could be mutated by id.
 *
 * @internal
 */
#[CoversClass(RowScopeGuard::class)]
final class RowScopeGuardTest extends TestCase
{
    use BuildsEntityManager;

    private const string SECRET = 'test-secret';

    private EntityManagerInterface $em;
    private AjaxDataTableRegistry $registry;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountAccount::class);

        foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'hidden'] as $id => $name) {
            $this->em->persist(new CountAccount($id, $name));
        }

        $this->em->flush();
        $this->em->clear();

        $locator = $this->createStub(ContainerInterface::class);
        $locator->method('get')->willReturn(new ScopedAccountDataTable($this->provider()));

        $this->registry = new AjaxDataTableRegistry($locator, new AjaxDataTableTokenManager(self::SECRET), [ScopedAccountDataTable::class => 'scoped']);
    }

    #[Test]
    public function it_rejects_an_id_outside_the_scope_exactly_like_a_missing_one(): void
    {
        foreach ([3, '3', 999] as $id) {
            try {
                $this->guard()->assertInScope($this->resolved(), $id);
                $this->fail('Out-of-scope id must be rejected.');
            } catch (EntityNotFoundException $exception) {
                $this->assertSame('Entity not found.', $exception->getMessage());
            }
        }

        $this->guard()->assertInScope($this->resolved(), 1);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_keeps_current_behavior_when_the_provider_cannot_check_the_scope(): void
    {
        $table = new ResolvedDataTable(new ScopedAccountDataTable($this->createStub(DataProviderInterface::class)), CountAccount::class, ScopedAccountDataTable::class);

        $this->guard()->assertInScope($table, 3);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_does_not_delete_a_row_outside_the_scope(): void
    {
        $controller = new AjaxDeleteController($this->mutator(), $this->tokenValidator(), $this->registry, new AuthorizationChecker(new TestAuthorizationChecker()), $this->guard());

        try {
            $controller($this->request(), new AjaxEntityQueryDto(dataTable: $this->actionToken(), id: 3));
            $this->fail('Out-of-scope delete must be rejected.');
        } catch (EntityNotFoundException) {
            $this->assertNotNull($this->em->find(CountAccount::class, 3));
        }

        $controller($this->request(), new AjaxEntityQueryDto(dataTable: $this->actionToken(), id: 1));
        $this->assertNull($this->em->find(CountAccount::class, 1));
    }

    #[Test]
    public function it_does_not_toggle_a_row_outside_the_scope(): void
    {
        $controller = new AjaxEditController($this->mutator(), $this->tokenValidator(), new BooleanMutationContextResolver($this->registry), $this->guard());

        try {
            $controller($this->request(), new AjaxEditRequestDto($this->actionToken(), 3, 'active', false));
            $this->fail('Out-of-scope toggle must be rejected.');
        } catch (EntityNotFoundException) {
            $this->assertTrue($this->em->find(CountAccount::class, 3)->active);
        }

        $controller($this->request(), new AjaxEditRequestDto($this->actionToken(), 2, 'active', false));
        $this->assertFalse($this->em->find(CountAccount::class, 2)->active);
    }

    #[Test]
    public function it_hides_the_detail_of_a_row_outside_the_scope(): void
    {
        $twig    = new Environment(new ArrayLoader(['detail.html.twig' => '{{ entity.name }}']));
        $service = new DetailRowService($this->locator(), $twig, new AuthorizationChecker(new TestAuthorizationChecker()), $this->guard());

        $this->assertSame(404, $service->handleView($this->resolved(), 3)->statusCode);
        $this->assertSame('Alpha', $service->handleView($this->resolved(), 1)->html);
    }

    #[Test]
    public function it_hides_the_edit_form_and_refuses_the_submit_of_a_row_outside_the_scope(): void
    {
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $renderer->expects($this->never())->method('renderBody');

        $service = new EditFormService(
            $this->locator(),
            new EditFormBuilder($this->createStub(FormFactoryInterface::class), new ColumnToFormTypeMapper()),
            $renderer,
            $this->createStub(EditModalTemplateResolver::class),
            new NullMercurePublisher(),
            new MercureTopicResolver(),
            new MutationFlusher(),
            new AuthorizationChecker(new TestAuthorizationChecker()),
            $this->guard(),
        );

        $this->assertSame(404, $service->handleView($this->resolved(), 3)->statusCode);
        $this->assertSame(404, $service->handleSubmit($this->resolved(), 3, ['name' => 'pwned'])->statusCode);
    }

    private function request(): Request
    {
        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        return $request;
    }

    private function resolved(): ResolvedDataTable
    {
        return $this->registry->resolveAction($this->actionToken());
    }

    private function actionToken(): string
    {
        return $this->registry->getActionToken(ScopedAccountDataTable::class) ?? '';
    }

    private function locator(): EntityLocator
    {
        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->em);

        return new EntityLocator($doctrine);
    }

    private function guard(): RowScopeGuard
    {
        return new RowScopeGuard($this->locator());
    }

    private function mutator(): EntityMutator
    {
        return new EntityMutator(
            $this->locator(),
            PropertyAccess::createPropertyAccessor(),
            new NullMercurePublisher(),
            new AuthorizationChecker(new TestAuthorizationChecker()),
            new MercureTopicResolver(),
            new MutationFlusher(),
        );
    }

    private function tokenValidator(): MutationTokenValidator
    {
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        return new MutationTokenValidator($csrf);
    }

    private function provider(): DoctrineDataProvider
    {
        $scope = static fn (QueryBuilder $qb): QueryBuilder => $qb->andWhere('e.name <> :hidden')->setParameter('hidden', 'hidden');

        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountAccount::class,
            configureBaseQueryBuilder: $scope,
            configureQueryBuilder: $scope,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    $item = $row instanceof RowContext ? $row->item : $row;

                    return ['id' => $item->id, 'name' => $item->name];
                }
            },
        );
    }
}

#[AsDataTable(entityClass: CountAccount::class)]
final class ScopedAccountDataTable extends AbstractDataTable
{
    public function __construct(private readonly DataProviderInterface $dataProvider)
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('name');
        yield BooleanColumn::new('active')->renderAsSwitch();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Action::delete())
            ->add(Action::edit())
            ->add(Action::detail()->collapsible('detail.html.twig'));
    }

    protected function createDataProvider(): DataProviderInterface
    {
        return $this->dataProvider;
    }
}
