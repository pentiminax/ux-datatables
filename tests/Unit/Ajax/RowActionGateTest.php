<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Ajax\AuthorizedRow;
use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Ajax\RowActionKind;
use Pentiminax\UX\DataTables\Ajax\RowActionRequest;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\InvalidBooleanMutationContextException;
use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Security\Permission;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountAccount;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\RelatedToggleCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\RelatedToggleOrder;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Account 3 and order 3 are hidden by the table scope; account 4 is outside it and also denied by row permission.
 * Orders 1 and 3 belong to customer 10, order 2 to customer 20.
 *
 * @internal
 */
#[CoversClass(RowActionGate::class)]
#[CoversClass(RowActionRequest::class)]
#[CoversClass(RowActionKind::class)]
final class RowActionGateTest extends TestCase
{
    use BuildsEntityManager;
    use CollectsDeprecations;

    private const SECRET = 'test-secret';

    private EntityManagerInterface $em;
    private AjaxDataTableRegistry $registry;

    /** @var list<string> */
    private array $deniedAttributes = [];

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountAccount::class, RelatedToggleCustomer::class, RelatedToggleOrder::class);

        foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'hidden'] as $id => $name) {
            $this->em->persist(new CountAccount($id, $name));
        }

        $customers = [10 => new RelatedToggleCustomer(10), 20 => new RelatedToggleCustomer(20), 30 => new RelatedToggleCustomer(30)];
        foreach ($customers as $customer) {
            $this->em->persist($customer);
        }

        $this->em->persist(new RelatedToggleOrder(1, 'open', $customers[10]));
        $this->em->persist(new RelatedToggleOrder(2, 'open', $customers[20]));
        $this->em->persist(new RelatedToggleOrder(3, 'hidden', $customers[10]));
        $this->em->flush();
        $this->em->clear();

        $tables = [
            'accounts'      => new GateAccountDataTable($this->provider(CountAccount::class, 'name')),
            'bare'          => new GateBareAccountDataTable($this->provider(CountAccount::class, 'name')),
            'denied-action' => new GateDeniedActionAccountDataTable($this->provider(CountAccount::class, 'name')),
            'orders'        => new GateOrderDataTable($this->provider(RelatedToggleOrder::class, 'label')),
            'manual'        => new GateManualDataTable(),
        ];

        $locator = $this->createStub(ContainerInterface::class);
        $locator->method('get')->willReturnCallback(static fn (string $id) => $tables[$id]);

        $this->registry = new AjaxDataTableRegistry($locator, new AjaxDataTableTokenManager(self::SECRET), [
            GateAccountDataTable::class             => 'accounts',
            GateBareAccountDataTable::class         => 'bare',
            GateDeniedActionAccountDataTable::class => 'denied-action',
            GateOrderDataTable::class               => 'orders',
            GateManualDataTable::class              => 'manual',
        ]);
    }

    /**
     * @return iterable<string, array{RowActionKind}>
     */
    public static function rowKinds(): iterable
    {
        yield 'delete' => [RowActionKind::Delete];
        yield 'detail' => [RowActionKind::Detail];
        yield 'edit form view' => [RowActionKind::EditFormView];
        yield 'edit form submit' => [RowActionKind::EditFormSubmit];
    }

    /**
     * @return iterable<string, array{RowActionKind}>
     */
    public static function mutatingKinds(): iterable
    {
        yield 'delete' => [RowActionKind::Delete];
        yield 'toggle' => [RowActionKind::Toggle];
        yield 'edit form submit' => [RowActionKind::EditFormSubmit];
    }

    /**
     * @return iterable<string, array{RowActionKind}>
     */
    public static function readingKinds(): iterable
    {
        yield 'detail' => [RowActionKind::Detail];
        yield 'edit form view' => [RowActionKind::EditFormView];
    }

    #[Test]
    #[DataProvider('rowKinds')]
    public function it_authorizes_an_in_scope_row(RowActionKind $kind): void
    {
        $authorized = $this->authorize($this->request($kind, 'accounts', 1));

        $this->assertInstanceOf(AuthorizedRow::class, $authorized);
        $this->assertSame(CountAccount::class, $authorized->table->entityClass);
        $this->assertNotNull($authorized->action);
        $this->assertInstanceOf(CountAccount::class, $authorized->context->entity);
        $this->assertSame(1, $authorized->context->entity->id);
        $this->assertNull($authorized->field);
        $this->assertNull($authorized->entityClass);
    }

    #[Test]
    public function it_authorizes_a_same_entity_toggle(): void
    {
        $authorized = $this->authorize(RowActionRequest::toggle($this->token(GateAccountDataTable::class), 'active', 2));

        $this->assertNull($authorized->action);
        $this->assertSame(GateAccountDataTable::class, $authorized->table->dataTableClass);
        $this->assertSame('active', $authorized->field);
        $this->assertSame(CountAccount::class, $authorized->entityClass);
        $this->assertSame(2, $authorized->context->entity->id);
    }

    #[Test]
    public function it_authorizes_a_related_entity_toggle_of_an_in_scope_row(): void
    {
        $authorized = $this->authorize(RowActionRequest::toggle($this->token(GateOrderDataTable::class), 'vip', 20, 2));

        $this->assertSame(RelatedToggleCustomer::class, $authorized->entityClass);
        $this->assertSame(RelatedToggleOrder::class, $authorized->table->entityClass);
        $this->assertSame(20, $authorized->context->entity->id);
    }

    #[Test]
    public function it_authorizes_a_toggle_on_a_table_without_an_entity_class_and_deprecates_it(): void
    {
        $authorized   = null;
        $deprecations = $this->withoutAuthorizationFallback($this->deprecationsOf(function () use (&$authorized): void {
            $authorized = $this->authorize(RowActionRequest::toggle($this->token(GateManualDataTable::class), 'vip', 30));
        }));

        $this->assertCount(1, $deprecations);
        $this->assertMatchesRegularExpression('/has no entity class, skips the row scope check: 2\.0 refuses the request/', $deprecations[0]);

        $this->assertNull($authorized->table->entityClass);
        $this->assertSame(30, $authorized->context->entity->id);
    }

    #[Test]
    #[DataProvider('mutatingKinds')]
    public function it_refuses_a_mutation_without_a_csrf_token(RowActionKind $kind): void
    {
        $this->expectException(InvalidCsrfTokenException::class);

        $this->authorize($this->request($kind, 'accounts', 1), csrf: false);
    }

    #[Test]
    #[DataProvider('readingKinds')]
    public function it_does_not_ask_a_csrf_token_of_a_read(RowActionKind $kind): void
    {
        $this->assertNotNull($this->authorize($this->request($kind, 'accounts', 1), csrf: false)->action);
    }

    #[Test]
    public function it_checks_the_csrf_token_before_resolving_the_table(): void
    {
        $this->expectException(InvalidCsrfTokenException::class);

        $this->authorize(RowActionRequest::delete('not-a-token', 1), csrf: false);
    }

    #[Test]
    #[DataProvider('rowKinds')]
    public function it_refuses_a_table_without_the_action(RowActionKind $kind): void
    {
        $this->expectException(MutationNotAllowedException::class);

        $this->authorize($this->request($kind, 'bare', 1));
    }

    #[Test]
    #[DataProvider('rowKinds')]
    public function it_refuses_an_action_the_static_permission_denies(RowActionKind $kind): void
    {
        $this->expectException(MutationNotAllowedException::class);

        $this->authorize($this->request($kind, 'denied-action', 1));
    }

    #[Test]
    #[DataProvider('rowKinds')]
    public function it_answers_a_row_outside_the_scope_as_not_found(RowActionKind $kind): void
    {
        $this->expectException(EntityNotFoundException::class);

        $this->authorize($this->request($kind, 'accounts', 3));
    }

    #[Test]
    #[DataProvider('rowKinds')]
    public function it_answers_a_missing_row_as_not_found(RowActionKind $kind): void
    {
        $this->expectException(EntityNotFoundException::class);

        $this->authorize($this->request($kind, 'accounts', 999));
    }

    /**
     * @return iterable<string, array{RowActionKind, string}>
     */
    public static function rowPermissions(): iterable
    {
        yield 'delete' => [RowActionKind::Delete, Permission::DT_DELETE_ROW];
        yield 'detail' => [RowActionKind::Detail, Permission::DT_VIEW_ROW_DETAILS];
        yield 'edit form view' => [RowActionKind::EditFormView, Permission::DT_EDIT_ROW];
        yield 'edit form submit' => [RowActionKind::EditFormSubmit, Permission::DT_EDIT_ROW];
        yield 'toggle' => [RowActionKind::Toggle, Permission::DT_EDIT_ROW];
    }

    #[Test]
    #[DataProvider('rowPermissions')]
    public function it_refuses_a_row_the_row_permission_denies(RowActionKind $kind, string $attribute): void
    {
        $this->deniedAttributes = [$attribute];

        $this->expectException(MutationNotAllowedException::class);

        $this->authorize($this->request($kind, 'accounts', 1));
    }

    #[Test]
    #[DataProvider('rowPermissions')]
    public function it_answers_an_out_of_scope_row_as_not_found_even_when_the_row_permission_denies(RowActionKind $kind, string $attribute): void
    {
        $this->deniedAttributes = [$attribute];

        $this->expectException(EntityNotFoundException::class);

        $this->authorize($this->request($kind, 'accounts', 3));
    }

    #[Test]
    public function it_refuses_a_toggle_of_a_field_that_is_not_a_switch(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);

        $this->authorize(RowActionRequest::toggle($this->token(GateAccountDataTable::class), 'name', 1));
    }

    #[Test]
    public function it_answers_a_related_toggle_without_a_row_id_as_not_found(): void
    {
        $this->expectException(EntityNotFoundException::class);

        $this->authorize(RowActionRequest::toggle($this->token(GateOrderDataTable::class), 'vip', 30));
    }

    #[Test]
    public function it_answers_a_related_toggle_of_an_out_of_scope_row_as_not_found(): void
    {
        $this->expectException(EntityNotFoundException::class);

        $this->authorize(RowActionRequest::toggle($this->token(GateOrderDataTable::class), 'vip', 10, 3));
    }

    #[Test]
    public function it_answers_a_related_toggle_the_row_does_not_own_as_not_found(): void
    {
        $this->expectException(EntityNotFoundException::class);

        $this->authorize(RowActionRequest::toggle($this->token(GateOrderDataTable::class), 'vip', 30, 1));
    }

    #[Test]
    public function it_answers_a_related_toggle_of_a_missing_row_as_not_found(): void
    {
        $this->expectException(EntityNotFoundException::class);

        $this->authorize(RowActionRequest::toggle($this->token(GateOrderDataTable::class), 'vip', 10, 999));
    }

    #[Test]
    public function it_refuses_a_related_toggle_the_row_permission_denies(): void
    {
        $this->deniedAttributes = [Permission::DT_EDIT_ROW];

        $this->expectException(MutationNotAllowedException::class);

        $this->authorize(RowActionRequest::toggle($this->token(GateOrderDataTable::class), 'vip', 20, 2));
    }

    #[Test]
    public function it_only_mutates_for_delete_toggle_and_edit_form_submit(): void
    {
        $mutating = array_filter(RowActionKind::cases(), static fn (RowActionKind $kind): bool => $kind->mutates());

        $this->assertSame([RowActionKind::Delete, RowActionKind::Toggle, RowActionKind::EditFormSubmit], array_values($mutating));
    }

    private function authorize(RowActionRequest $rowAction, bool $csrf = true): AuthorizedRow
    {
        $request = new Request();
        if ($csrf) {
            $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');
        }

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->em);
        $locator = new EntityLocator($doctrine);

        $csrfManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn(true);

        $denied  = $this->deniedAttributes;
        $checker = new AuthorizationChecker(new class($denied) implements AuthorizationCheckerInterface {
            /** @param list<string> $denied */
            public function __construct(private readonly array $denied)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                if (\in_array($attribute, $this->denied, true)) {
                    return false;
                }

                return !$subject instanceof \Pentiminax\UX\DataTables\Security\ActionPermissionContext
                    || 'ROLE_DENIED' !== $subject->action->getPermission();
            }
        });

        $gate = new RowActionGate(
            new MutationTokenValidator($csrfManager),
            $this->registry,
            new BooleanMutationContextResolver($this->registry, $checker),
            new RowScopeGuard($locator),
            $locator,
            $checker,
        );

        return $gate->authorize($request, $rowAction);
    }

    private function request(RowActionKind $kind, string $table, int $id): RowActionRequest
    {
        $class = match ($table) {
            'accounts'      => GateAccountDataTable::class,
            'bare'          => GateBareAccountDataTable::class,
            'denied-action' => GateDeniedActionAccountDataTable::class,
        };
        $token = $this->token($class);

        return match ($kind) {
            RowActionKind::Delete         => RowActionRequest::delete($token, $id),
            RowActionKind::Detail         => RowActionRequest::detail($token, $id),
            RowActionKind::EditFormView   => RowActionRequest::editFormView($token, $id),
            RowActionKind::EditFormSubmit => RowActionRequest::editFormSubmit($token, $id),
            RowActionKind::Toggle         => RowActionRequest::toggle($token, 'active', $id),
        };
    }

    /**
     * @param class-string<AbstractDataTable> $class
     */
    private function token(string $class): string
    {
        return $this->registry->getActionToken($class) ?? '';
    }

    /**
     * @param class-string $entityClass
     */
    private function provider(string $entityClass, string $scopeField): DoctrineDataProvider
    {
        $scope = static fn (QueryBuilder $qb): QueryBuilder => $qb->andWhere(\sprintf('e.%s <> :hidden', $scopeField))->setParameter('hidden', 'hidden');

        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: $entityClass,
            configureBaseQueryBuilder: $scope,
            configureQueryBuilder: $scope,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    $item = $row instanceof RowContext ? $row->item : $row;

                    return ['id' => $item->id];
                }
            },
        );
    }
}

abstract class GateDataTable extends AbstractDataTable
{
    public function __construct(private readonly DataProviderInterface $dataProvider)
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    protected function createDataProvider(): DataProviderInterface
    {
        return $this->dataProvider;
    }
}

#[AsDataTable(entityClass: CountAccount::class)]
final class GateAccountDataTable extends GateDataTable
{
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
}

#[AsDataTable(entityClass: CountAccount::class)]
final class GateBareAccountDataTable extends GateDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('name');
    }
}

#[AsDataTable(entityClass: CountAccount::class)]
final class GateDeniedActionAccountDataTable extends GateDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('name');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Action::delete()->permission('ROLE_DENIED'))
            ->add(Action::edit()->permission('ROLE_DENIED'))
            ->add(Action::detail()->collapsible('detail.html.twig')->permission('ROLE_DENIED'));
    }
}

#[AsDataTable(entityClass: RelatedToggleOrder::class)]
final class GateOrderDataTable extends GateDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('label');
        yield BooleanColumn::new('vip')
            ->renderAsSwitch()
            ->entityClass(RelatedToggleCustomer::class)
            ->toggleAjax(idField: 'customer.id');
    }
}

final class GateManualDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('vip')
            ->renderAsSwitch()
            ->entityClass(RelatedToggleCustomer::class);
    }
}
