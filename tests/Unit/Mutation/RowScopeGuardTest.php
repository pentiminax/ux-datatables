<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Ajax\DetailRowService;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Controller\AjaxDeleteController;
use Pentiminax\UX\DataTables\Controller\AjaxDetailController;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormController;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormSubmitController;
use Pentiminax\UX\DataTables\Controller\AjaxEditRequestDto;
use Pentiminax\UX\DataTables\Controller\AjaxEntityQueryDto;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
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
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\Mutation\RequestQueryHydrator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Runtime\TableDefinitionBuilder;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountAccount;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRowActionGate;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
    use BuildsRowActionGate;
    use BuildsRuntime;
    use CollectsDeprecations;

    private static function requestStack(): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(new Request());

        return $stack;
    }

    private const SECRET = 'test-secret';

    private EntityManagerInterface $em;
    private AjaxDataTableRegistry $registry;
    private TableDefinitionBuilder $definitions;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountAccount::class);

        foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'hidden'] as $id => $name) {
            $this->em->persist(new CountAccount($id, $name));
        }

        $this->em->flush();
        $this->em->clear();

        $this->definitions = new TableDefinitionBuilder(new ColumnResolver(new AuthorizationChecker()));

        $locator = $this->createStub(ContainerInterface::class);
        $locator->method('get')->willReturn(new ScopedAccountDataTable($this->provider()));

        $this->registry = new AjaxDataTableRegistry($locator, new AjaxDataTableTokenManager(self::SECRET), [ScopedAccountDataTable::class => 'scoped'], new AuthorizationChecker(), $this->definitions);
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
    public function it_refuses_a_row_when_the_provider_cannot_check_the_scope(): void
    {
        $table = $this->resolvedOf(new ScopedAccountDataTable($this->createStub(DataProviderInterface::class)));

        $this->expectException(MutationNotAllowedException::class);

        $this->guard()->assertInScope($table, 1);
    }

    #[Test]
    public function it_refuses_a_provider_whose_supports_throws_an_unhandled_match_error_for_the_row_scope(): void
    {
        $provider = new class implements DataProviderInterface, IdentifierDataProviderInterface {
            public function supports(DataSourceFeature $feature): bool
            {
                return match ($feature) {
                    DataSourceFeature::SearchBuilder => true,
                };
            }

            public function iterateRows(DataTableRequest $request): iterable
            {
                return [];
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
            }

            public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array
            {
                return [1];
            }

            public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array
            {
                return $ids;
            }
        };

        $this->expectException(MutationNotAllowedException::class);

        $this->guard()->assertInScope($this->resolvedOf(new ScopedAccountDataTable($provider)), 1);
    }

    #[Test]
    public function it_keeps_every_id_when_the_table_opts_out_of_the_scope_check(): void
    {
        $table = $this->resolvedOf(new ScopedAccountDataTable($this->createStub(DataProviderInterface::class), unscopedMutations: true));

        $this->guard()->assertInScope($table, 3);
        $this->assertSame([3, 999], $this->guard()->keepInScope($table, new Request(), 'id', [3, 999]));
    }

    #[Test]
    public function it_refuses_a_row_without_a_current_request(): void
    {
        $this->expectException(MutationNotAllowedException::class);

        (new RowScopeGuard($this->locator(), $this->runtime()))->assertInScope($this->resolved(), 1);
    }

    #[Test]
    public function it_refuses_an_entity_without_an_identifier_field(): void
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifier')->willReturn([]);

        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturn($metadata);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($manager);

        $this->expectException(MutationNotAllowedException::class);

        (new RowScopeGuard(new EntityLocator($doctrine), $this->runtime()))->assertInScope($this->resolved(), 1);
    }

    #[Test]
    public function it_raises_nothing_for_the_doctrine_provider(): void
    {
        $this->assertSame([], $this->scopeDeprecationsOf(fn () => $this->guard()->assertInScope($this->resolved(), 1)));
    }

    #[Test]
    public function it_does_not_delete_a_row_outside_the_scope(): void
    {
        $controller = new AjaxDeleteController($this->mutator(), $this->gate($this->registry, $this->guard()));

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
        $controller = new AjaxEditController($this->mutator(), $this->gate($this->registry, $this->guard()));

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
    public function it_replays_forwarded_query_parameters_on_delete_and_toggle(): void
    {
        $stack = $this->forwardingRequestStack(['hidden' => 'hidden', 'search' => ['value' => 'ignored']]);
        $guard = new RowScopeGuard($this->locator(), $this->runtime(), $stack);
        $token = $this->forwardingToken();

        $delete = new AjaxDeleteController($this->mutator(), $this->gate($this->forwardingRegistry(), $guard));
        $delete($stack->getCurrentRequest(), new AjaxEntityQueryDto($token, 1));
        $this->assertNull($this->em->find(CountAccount::class, 1));

        $toggle = new AjaxEditController($this->mutator(), $this->gate($this->forwardingRegistry(), $guard));
        $toggle($stack->getCurrentRequest(), new AjaxEditRequestDto($token, 2, 'active', false));
        $this->assertFalse($this->em->find(CountAccount::class, 2)->active);
    }

    #[Test]
    public function it_replays_forwarded_query_parameters_on_detail(): void
    {
        $stack = $this->forwardingRequestStack(['hidden' => 'hidden']);
        $guard = new RowScopeGuard($this->locator(), $this->runtime(), $stack);
        $token = $this->forwardingToken();

        $twig    = new Environment(new ArrayLoader(['detail.html.twig' => '{{ entity.name }}']));
        $service = new DetailRowService($twig);
        $detail  = new AjaxDetailController($service, $this->gate($this->forwardingRegistry(), $guard));

        $this->assertSame('Beta', json_decode($detail($stack->getCurrentRequest(), new AjaxEntityQueryDto($token, 2))->getContent(), true)['html']);

        $this->expectException(EntityNotFoundException::class);
        $detail($stack->getCurrentRequest(), new AjaxEntityQueryDto($token, 3));
    }

    #[Test]
    public function it_replays_forwarded_query_parameters_on_the_edit_form_view_and_submit(): void
    {
        $stack   = $this->forwardingRequestStack(['hidden' => 'hidden']);
        $guard   = new RowScopeGuard($this->locator(), $this->runtime(), $stack);
        $service = $this->forwardingEditFormService($guard);
        $token   = $this->forwardingToken();
        $view    = new AjaxEditFormController($service, $this->gate($this->forwardingRegistry(), $guard));
        $submit  = new AjaxEditFormSubmitController($service, $this->gate($this->forwardingRegistry(), $guard));
        $request = $stack->getCurrentRequest();

        $this->assertSame(200, $view($request, new AjaxEntityQueryDto($token, 1))->getStatusCode());
        $this->assertSame(200, $submit($request, new AjaxEditFormRequestDto($token, 1, ['name' => 'Renamed']))->getStatusCode());

        $this->assertNotNull($this->em->find(CountAccount::class, 3));
        $this->expectException(EntityNotFoundException::class);
        $view($request, new AjaxEntityQueryDto($token, 3));
    }

    #[Test]
    public function it_still_hides_a_row_outside_the_scope_of_the_forwarded_parameters(): void
    {
        $stack = $this->forwardingRequestStack(['hidden' => 'hidden']);
        $guard = new RowScopeGuard($this->locator(), $this->runtime(), $stack);
        $table = $this->forwardingRegistry()->resolveAction($this->forwardingToken());

        $this->expectException(EntityNotFoundException::class);
        $guard->assertInScope($table, 3);
    }

    #[Test]
    public function it_excludes_every_row_when_the_body_forwards_nothing(): void
    {
        $guard = new RowScopeGuard($this->locator(), $this->runtime(), $this->forwardingRequestStack([]));
        $table = $this->forwardingRegistry()->resolveAction($this->forwardingToken());

        $this->expectException(EntityNotFoundException::class);
        $guard->assertInScope($table, 1);
    }

    #[Test]
    public function it_ignores_undeclared_names_and_a_missing_query_field(): void
    {
        $request = Request::create('/', 'POST', content: json_encode(['query' => ['hidden' => 'a', 'search' => ['value' => 'x']]], \JSON_THROW_ON_ERROR));

        $this->assertSame(['hidden' => 'a'], RequestQueryHydrator::forwardedFromBody($request, ['hidden', 'absent']));
        $this->assertSame([], RequestQueryHydrator::forwardedFromBody(Request::create('/', 'POST', content: '{}'), ['hidden']));
        $this->assertSame([], RequestQueryHydrator::forwardedFromBody(Request::create('/', 'POST', content: 'nope'), ['hidden']));
    }

    private function forwardingRegistry(): AjaxDataTableRegistry
    {
        $locator = $this->createStub(ContainerInterface::class);
        $locator->method('get')->willReturnCallback(fn (): ForwardedScopeAccountDataTable => new ForwardedScopeAccountDataTable($this->em));

        return new AjaxDataTableRegistry($locator, new AjaxDataTableTokenManager(self::SECRET), [ForwardedScopeAccountDataTable::class => 'forwarded'], new AuthorizationChecker(), $this->definitions);
    }

    private function forwardingEditFormService(RowScopeGuard $guard): EditFormService
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('isValid')->willReturn(true);
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('getForm')->willReturn($form);
        $factory = $this->createStub(FormFactoryInterface::class);
        $factory->method('createBuilder')->willReturn($builder);

        $renderer = $this->createStub(EditModalRenderer::class);
        $renderer->method('render')->willReturn('<form></form>');

        return new EditFormService(
            new EditFormBuilder($factory, new ColumnToFormTypeMapper()),
            $renderer,
            $this->createStub(EditModalTemplateResolver::class),
            new NullMercurePublisher(),
            new MercureTopicResolver(),
            new MutationFlusher(),
            new ColumnResolver(new AuthorizationChecker(new TestAuthorizationChecker())),
        );
    }

    private function forwardingToken(): string
    {
        return $this->forwardingRegistry()->getActionToken(ForwardedScopeAccountDataTable::class) ?? '';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function forwardingRequestStack(array $query): RequestStack
    {
        $request = Request::create('/datatables/ajax/delete', 'POST', content: json_encode(['query' => $query], \JSON_THROW_ON_ERROR));
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }

    private function request(): Request
    {
        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        return $request;
    }

    /**
     * @return list<string>
     */
    private function scopeDeprecationsOf(callable $call): array
    {
        return $this->withoutAuthorizationFallback($this->deprecationsOf($call));
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

    private function runtime(): DataTableRuntime
    {
        return self::buildRuntime(definitions: $this->definitions);
    }

    private function resolvedOf(ScopedAccountDataTable $table): ResolvedDataTable
    {
        return new ResolvedDataTable($table, CountAccount::class, ScopedAccountDataTable::class, $this->definitions->build($table));
    }

    private function guard(): RowScopeGuard
    {
        return new RowScopeGuard($this->locator(), $this->runtime(), self::requestStack());
    }

    private function mutator(): EntityMutator
    {
        return new EntityMutator(
            PropertyAccess::createPropertyAccessor(),
            new NullMercurePublisher(),
            new MercureTopicResolver(),
            new MutationFlusher(),
        );
    }

    private function gate(AjaxDataTableRegistry $registry, RowScopeGuard $guard): RowActionGate
    {
        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->em);

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        return $this->rowActionGate($registry, $doctrine, $csrf, $guard);
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
class ScopedAccountDataTable extends AbstractDataTable
{
    public function __construct(private readonly DataProviderInterface $dataProvider, private readonly bool $unscopedMutations = false)
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide()->unscopedMutations($this->unscopedMutations);
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

    public function createDataProvider(): DataProviderInterface
    {
        return $this->dataProvider;
    }
}

#[AsDataTable(entityClass: CountAccount::class)]
final class ForwardedScopeAccountDataTable extends AbstractDataTable
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide()->forwardQueryParameters(['hidden']);
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

    public function createDataProvider(): DataProviderInterface
    {
        $scope = fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name <> :hidden')
            ->setParameter('hidden', $this->getHttpRequest()?->query->get('hidden'));

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
