<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Controller\AjaxEditRequestDto;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\RelatedToggleCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\RelatedToggleOrder;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Order 3 is hidden by the table scope. Orders 1 and 3 belong to customer 10, order 2 to customer 20.
 *
 * @internal
 */
#[CoversClass(AjaxEditController::class)]
#[CoversClass(RowScopeGuard::class)]
final class AjaxEditRelatedEntityTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;
    private ManagerRegistry $doctrine;
    private AjaxEditController $controller;
    private string $token;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(RelatedToggleCustomer::class, RelatedToggleOrder::class);

        $customers = [10 => new RelatedToggleCustomer(10), 20 => new RelatedToggleCustomer(20), 30 => new RelatedToggleCustomer(30)];
        foreach ($customers as $customer) {
            $this->em->persist($customer);
        }

        $this->em->persist(new RelatedToggleOrder(1, 'open', $customers[10]));
        $this->em->persist(new RelatedToggleOrder(2, 'open', $customers[20]));
        $this->em->persist(new RelatedToggleOrder(3, 'hidden', $customers[10]));
        $this->em->flush();
        $this->em->clear();

        $this->doctrine = $this->createStub(ManagerRegistry::class);
        $this->doctrine->method('getManagerForClass')->willReturn($this->em);

        $registry    = $this->registry(new RelatedToggleOrderDataTable($this->provider()), RelatedToggleOrderDataTable::class);
        $this->token = $registry->getActionToken(RelatedToggleOrderDataTable::class) ?? '';

        $this->controller = $this->controller($registry);
    }

    #[Test]
    public function it_toggles_the_related_entity_of_an_in_scope_row(): void
    {
        $this->toggle(20, rowId: 2);

        $this->assertTrue($this->em->find(RelatedToggleCustomer::class, 20)->vip);
    }

    #[Test]
    public function it_rejects_a_row_outside_the_scope(): void
    {
        $this->assertRejected(10, rowId: 3);
    }

    #[Test]
    public function it_rejects_a_related_id_that_does_not_belong_to_the_row(): void
    {
        $this->assertRejected(10, rowId: 2);
        $this->assertRejected(30, rowId: 1);
    }

    #[Test]
    public function it_rejects_a_missing_row(): void
    {
        $this->assertRejected(10, rowId: 999);
    }

    #[Test]
    public function it_rejects_a_toggle_without_a_row_id(): void
    {
        $this->assertRejected(30, rowId: null);
    }

    #[Test]
    public function it_toggles_a_switch_on_a_table_without_an_entity_class(): void
    {
        $registry = $this->registry(new RelatedToggleManualDataTable(), RelatedToggleManualDataTable::class);
        $token    = $registry->getActionToken(RelatedToggleManualDataTable::class) ?? '';

        $controller = $this->controller($registry);

        $controller($this->request(), new AjaxEditRequestDto($token, 30, 'vip', true));

        $this->assertTrue($this->em->find(RelatedToggleCustomer::class, 30)->vip);
    }

    private function assertRejected(int $customerId, ?int $rowId): void
    {
        try {
            $this->toggle($customerId, $rowId);
            $this->fail('The toggle must be rejected.');
        } catch (EntityNotFoundException) {
            $this->assertFalse($this->em->find(RelatedToggleCustomer::class, $customerId)->vip);
        }
    }

    private function toggle(int $customerId, ?int $rowId): void
    {
        ($this->controller)($this->request(), new AjaxEditRequestDto($this->token, $customerId, 'vip', true, rowId: $rowId));
    }

    private function request(): Request
    {
        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        return $request;
    }

    private function controller(AjaxDataTableRegistry $registry): AjaxEditController
    {
        $locator     = new EntityLocator($this->doctrine);
        $permissions = new AuthorizationChecker(new TestAuthorizationChecker());

        return new AjaxEditController(
            new EntityMutator($locator, PropertyAccess::createPropertyAccessor(), new NullMercurePublisher(), $permissions, new MercureTopicResolver(), new MutationFlusher()),
            new RowActionGate($this->tokenValidator(), $registry, new BooleanMutationContextResolver($registry), new RowScopeGuard($locator), $locator, $permissions),
        );
    }

    private function tokenValidator(): MutationTokenValidator
    {
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        return new MutationTokenValidator($csrf);
    }

    /**
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    private function registry(AbstractDataTable $table, string $dataTableClass): AjaxDataTableRegistry
    {
        $containerLocator = $this->createStub(ContainerInterface::class);
        $containerLocator->method('get')->willReturn($table);

        return new AjaxDataTableRegistry($containerLocator, new AjaxDataTableTokenManager('test-secret'), [$dataTableClass => 'orders']);
    }

    private function provider(): DoctrineDataProvider
    {
        $scope = static fn (QueryBuilder $qb): QueryBuilder => $qb->andWhere('e.label <> :hidden')->setParameter('hidden', 'hidden');

        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: RelatedToggleOrder::class,
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

#[AsDataTable(entityClass: RelatedToggleOrder::class)]
final class RelatedToggleOrderDataTable extends AbstractDataTable
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
        yield TextColumn::new('label');
        yield BooleanColumn::new('vip')
            ->renderAsSwitch()
            ->entityClass(RelatedToggleCustomer::class)
            ->toggleAjax(idField: 'customer.id');
    }

    protected function createDataProvider(): DataProviderInterface
    {
        return $this->dataProvider;
    }
}

final class RelatedToggleManualDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('vip')
            ->renderAsSwitch()
            ->entityClass(RelatedToggleCustomer::class);
    }
}
