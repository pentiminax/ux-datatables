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
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\Runtime\DataTableCall;
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
                $this->guard()->assertInScope($this->resolved(), $this->callOf($this->resolved()), $id);
                $this->fail('Out-of-scope id must be rejected.');
            } catch (EntityNotFoundException $exception) {
                $this->assertSame('Entity not found.', $exception->getMessage());
            }
        }

        $this->guard()->assertInScope($this->resolved(), $this->callOf($this->resolved()), 1);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_refuses_a_row_when_the_provider_cannot_check_the_scope(): void
    {
        $table = $this->resolvedOf(new ScopedAccountDataTable($this->createStub(DataProviderInterface::class)));

        $this->expectException(MutationNotAllowedException::class);

        $this->guard()->assertInScope($table, $this->callOf($table), 1);
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

        $table = $this->resolvedOf(new ScopedAccountDataTable($provider));
        $this->guard()->assertInScope($table, $this->callOf($table), 1);
    }

    #[Test]
    public function it_keeps_every_id_when_the_table_opts_out_of_the_scope_check(): void
    {
        $table = $this->resolvedOf(new ScopedAccountDataTable($this->createStub(DataProviderInterface::class), unscopedMutations: true));

        $this->guard()->assertInScope($table, $this->callOf($table), 3);
        $this->assertSame([3, 999], $this->guard()->keepInScope($table, new Request(), 'id', [3, 999]));
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

        (new RowScopeGuard(new EntityLocator($doctrine), $this->runtime()))->assertInScope($this->resolved(), $this->callOf($this->resolved()), 1);
    }

    #[Test]
    public function it_raises_nothing_for_the_doctrine_provider(): void
    {
        $this->assertSame([], $this->scopeDeprecationsOf(fn () => $this->guard()->assertInScope($this->resolved(), $this->callOf($this->resolved()), 1)));
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
        $query = ['hidden' => 'hidden', 'search' => ['value' => 'ignored']];
        $token = $this->forwardingToken();
        $guard = $this->guard();

        $delete = new AjaxDeleteController($this->mutator(), $this->gate($this->forwardingRegistry(), $guard));
        $delete($this->request(), new AjaxEntityQueryDto($token, 1, $query));
        $this->assertNull($this->em->find(CountAccount::class, 1));

        $toggle = new AjaxEditController($this->mutator(), $this->gate($this->forwardingRegistry(), $guard));
        $toggle($this->request(), new AjaxEditRequestDto($token, 2, 'active', false, query: $query));
        $this->assertFalse($this->em->find(CountAccount::class, 2)->active);
    }

    #[Test]
    public function it_replays_forwarded_query_parameters_on_detail(): void
    {
        $query = ['hidden' => 'hidden'];
        $token = $this->forwardingToken();

        $twig    = new Environment(new ArrayLoader(['detail.html.twig' => '{{ entity.name }}']));
        $service = new DetailRowService($twig);
        $detail  = new AjaxDetailController($service, $this->gate($this->forwardingRegistry(), $this->guard()));

        $this->assertSame('Beta', json_decode($detail($this->request(), new AjaxEntityQueryDto($token, 2, $query))->getContent(), true)['html']);

        $this->expectException(EntityNotFoundException::class);
        $detail($this->request(), new AjaxEntityQueryDto($token, 3, $query));
    }

    #[Test]
    public function it_replays_forwarded_query_parameters_on_the_edit_form_view_and_submit(): void
    {
        $query   = ['hidden' => 'hidden'];
        $guard   = $this->guard();
        $service = $this->forwardingEditFormService($guard);
        $token   = $this->forwardingToken();
        $view    = new AjaxEditFormController($service, $this->gate($this->forwardingRegistry(), $guard));
        $submit  = new AjaxEditFormSubmitController($service, $this->gate($this->forwardingRegistry(), $guard));
        $request = $this->request();

        $this->assertSame(200, $view($request, new AjaxEntityQueryDto($token, 1, $query))->getStatusCode());
        $this->assertSame(200, $submit($request, new AjaxEditFormRequestDto($token, 1, ['name' => 'Renamed'], $query))->getStatusCode());

        $this->assertNotNull($this->em->find(CountAccount::class, 3));
        $this->expectException(EntityNotFoundException::class);
        $view($request, new AjaxEntityQueryDto($token, 3, $query));
    }

    #[Test]
    public function it_excludes_every_row_when_the_payload_forwards_nothing(): void
    {
        $delete = new AjaxDeleteController($this->mutator(), $this->gate($this->forwardingRegistry(), $this->guard()));

        $this->expectException(EntityNotFoundException::class);
        $delete($this->request(), new AjaxEntityQueryDto($this->forwardingToken(), 1));
    }

    #[Test]
    public function it_ignores_a_forwarded_parameter_the_table_did_not_declare(): void
    {
        $delete = new AjaxDeleteController($this->mutator(), $this->gate($this->forwardingRegistry(), $this->guard()));

        try {
            $delete($this->request(), new AjaxEntityQueryDto($this->forwardingToken(), 1, ['hidden' => 'hidden', 'extra' => 'Alpha']));
        } catch (EntityNotFoundException) {
            $this->fail('A declared parameter must scope the row.');
        }

        $this->assertNull($this->em->find(CountAccount::class, 1));
    }

    #[Test]
    public function it_leaves_the_incoming_request_unmodified(): void
    {
        $request = $this->request();
        $delete  = new AjaxDeleteController($this->mutator(), $this->gate($this->forwardingRegistry(), $this->guard()));

        $delete($request, new AjaxEntityQueryDto($this->forwardingToken(), 1, ['hidden' => 'hidden']));

        $this->assertSame([], $request->query->all());
        $this->assertSame([], $request->request->all());
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
            new MercureTopicResolver(\Pentiminax\UX\DataTables\Tests\Support\TestRuntime::make()),
            new MutationFlusher(),
            new ColumnResolver(new AuthorizationChecker(new TestAuthorizationChecker())),
        );
    }

    private function forwardingToken(): string
    {
        return $this->forwardingRegistry()->getActionToken(ForwardedScopeAccountDataTable::class) ?? '';
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

    private function callOf(ResolvedDataTable $table): DataTableCall
    {
        return $this->runtime()->handle($table->table, new Request());
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
        return new RowScopeGuard($this->locator(), $this->runtime());
    }

    private function mutator(): EntityMutator
    {
        return new EntityMutator(
            PropertyAccess::createPropertyAccessor(),
            new NullMercurePublisher(),
            new MercureTopicResolver(\Pentiminax\UX\DataTables\Tests\Support\TestRuntime::make()),
            new MutationFlusher(),
        );
    }

    private function gate(AjaxDataTableRegistry $registry, RowScopeGuard $guard): RowActionGate
    {
        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->em);

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        return $this->rowActionGate($registry, $doctrine, $csrf, $guard, runtime: $this->runtime());
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

    protected function createDataProvider(): DataProviderInterface
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

    protected function createDataProvider(): DataProviderInterface
    {
        $scope = fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name <> :hidden')
            ->andWhere('e.name <> :extra')
            ->setParameter('hidden', $this->getHttpRequest()?->query->get('hidden'))
            ->setParameter('extra', $this->getHttpRequest()?->query->get('extra', 'none'));

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
