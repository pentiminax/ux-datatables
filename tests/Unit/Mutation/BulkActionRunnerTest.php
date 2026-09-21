<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierCollectingDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Exception\InvalidBulkSelectionException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Mutation\BulkActionContext;
use Pentiminax\UX\DataTables\Mutation\BulkActionRunner;
use Pentiminax\UX\DataTables\Mutation\BulkRecords;
use Pentiminax\UX\DataTables\Mutation\BulkSelection;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(BulkActionRunner::class)]
final class BulkActionRunnerTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountTag::class);

        foreach (['Alpha', 'Beta', 'Gamma', 'Delta'] as $index => $name) {
            $this->em->persist(new CountCustomer($index + 1, $name));
        }

        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_hands_the_selected_entities_to_the_handler_and_persists_their_changes(): void
    {
        $seen   = [];
        $action = BulkAction::new('rename')->handler(function (BulkRecords $records) use (&$seen): void {
            foreach ($records as $customer) {
                $seen[]         = $customer->name;
                $customer->name = strtoupper($customer->name);
            }
        });

        $result = $this->runner()->run($this->resolved($action), $action, new BulkSelection(ids: [1, 3]), new Request());

        $this->assertSame(['Alpha', 'Gamma'], $seen);
        $this->assertSame(2, $result->processed);
        $this->assertSame(0, $result->skipped);

        $this->em->clear();
        $this->assertSame('ALPHA', $this->em->find(CountCustomer::class, 1)->name);
        $this->assertSame('GAMMA', $this->em->find(CountCustomer::class, 3)->name);
        $this->assertSame('Beta', $this->em->find(CountCustomer::class, 2)->name);
    }

    #[Test]
    public function it_walks_the_selection_one_chunk_at_a_time(): void
    {
        $flushes = 0;

        $this->em->getEventManager()->addEventListener(
            Events::postFlush,
            new class($flushes) {
                public function __construct(private int &$flushes)
                {
                }

                public function postFlush(): void
                {
                    ++$this->flushes;
                }
            },
        );

        $action = BulkAction::new('touch')->chunk(2)->handler(static function (BulkRecords $records): void {
            foreach ($records as $ignored) {
            }
        });

        $this->runner()->run(
            $this->resolved($action),
            $action,
            new BulkSelection(ids: [1, 2, 3, 4]),
            new Request(),
        );

        // Two chunks, plus the trailing flush that covers a handler stopping mid-chunk.
        $this->assertSame(3, $flushes);
    }

    #[Test]
    public function it_excludes_and_counts_entities_the_user_may_not_act_on(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (string $attribute, mixed $subject = null): bool => !(
                Permission::DT_EXECUTE_ACTION === $attribute
                && $subject instanceof ActionPermissionContext
                && $subject->currentSource instanceof CountCustomer
                && 'Beta' === $subject->currentSource->name
            )
        );

        $seen   = [];
        $action = BulkAction::new('touch')
            ->setPermission('CUSTOMER_TOUCH', static fn (CountCustomer $c): CountCustomer => $c)
            ->handler(function (BulkRecords $records) use (&$seen): void {
                foreach ($records as $customer) {
                    $seen[] = $customer->name;
                }
            });

        $result = $this->runner(permissionChecker: new AuthorizationChecker($checker))->run(
            $this->resolved($action),
            $action,
            new BulkSelection(ids: [1, 2, 3]),
            new Request(),
        );

        $this->assertSame(['Alpha', 'Gamma'], $seen);
        $this->assertSame(2, $result->processed);
        $this->assertSame(1, $result->skipped);
    }

    #[Test]
    public function it_counts_a_selected_row_that_no_longer_exists_as_skipped(): void
    {
        $action = BulkAction::new('touch')->handler(static function (BulkRecords $records): void {
            foreach ($records as $ignored) {
            }
        });

        $result = $this->runner()->run(
            $this->resolved($action),
            $action,
            new BulkSelection(ids: [1, 999]),
            new Request(),
        );

        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->skipped);
    }

    #[Test]
    public function it_loads_records_by_the_configured_identifier_field(): void
    {
        $seen   = [];
        $action = BulkAction::new('touch')->handler(function (BulkRecords $records) use (&$seen): void {
            foreach ($records as $customer) {
                $seen[] = $customer->name;
            }
        });
        $table = $this->resolved($action);
        $table->table->getConfiguredDataTable()->getBulkActions()?->setIdField('name');

        $this->runner()->run($table, $action, new BulkSelection(ids: ['Beta']), new Request());

        $this->assertSame(['Beta'], $seen);
    }

    #[Test]
    public function it_publishes_one_mercure_message_for_the_whole_batch(): void
    {
        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/customers'], ['type' => 'bulk', 'action' => 'touch', 'processed' => 3]);

        $topicResolver = $this->createStub(MercureTopicResolver::class);
        $topicResolver->method('resolve')->willReturn(['/customers']);

        $action = BulkAction::new('touch')->chunk(1)->handler(static function (BulkRecords $records): void {
            foreach ($records as $ignored) {
            }
        });

        $this->runner(publisher: $publisher, topicResolver: $topicResolver)->run(
            $this->resolved($action),
            $action,
            new BulkSelection(ids: [1, 2, 3]),
            new Request(),
        );
    }

    #[Test]
    public function it_publishes_nothing_when_every_selected_row_was_skipped(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (string $attribute, mixed $subject = null): bool => !(
                $subject instanceof ActionPermissionContext && $subject->hasRowContext
            )
        );

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $action = BulkAction::new('touch')
            ->setPermission('CUSTOMER_TOUCH', static fn (CountCustomer $c): CountCustomer => $c)
            ->handler(static function (BulkRecords $records): void {
                foreach ($records as $ignored) {
                }
            });

        $result = $this->runner(
            permissionChecker: new AuthorizationChecker($checker),
            publisher: $publisher,
        )->run($this->resolved($action), $action, new BulkSelection(ids: [1, 2]), new Request());

        $this->assertSame(0, $result->processed);
        $this->assertSame(2, $result->skipped);
    }

    #[Test]
    public function it_reports_the_selected_count_and_the_running_totals_to_the_handler(): void
    {
        $selectedDuringRun = null;
        $processedAtEnd    = null;

        $action = BulkAction::new('touch')->handler(
            static function (BulkRecords $records, BulkActionContext $context) use (&$selectedDuringRun, &$processedAtEnd): void {
                $selectedDuringRun = $records->count();

                foreach ($records as $ignored) {
                }

                $processedAtEnd = $context->processedCount();
            }
        );

        $this->runner()->run($this->resolved($action), $action, new BulkSelection(ids: [1, 2]), new Request());

        $this->assertSame(2, $selectedDuringRun);
        $this->assertSame(2, $processedAtEnd);
    }

    #[Test]
    public function it_rejects_an_empty_selection(): void
    {
        $action = BulkAction::new('touch')->handler(static fn () => null);

        $this->expectException(InvalidBulkSelectionException::class);
        $this->expectExceptionMessage('No row is selected.');

        $this->runner()->run($this->resolved($action), $action, new BulkSelection(ids: []), new Request());
    }

    #[Test]
    public function it_rejects_an_action_without_a_handler(): void
    {
        $action = BulkAction::new('touch');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Bulk action "touch" must declare a handler.');

        $this->runner()->run($this->resolved($action), $action, new BulkSelection(ids: [1]), new Request());
    }

    #[Test]
    public function it_resolves_every_row_matching_the_displayed_request(): void
    {
        $seen   = [];
        $action = BulkAction::new('touch')->handler(function (BulkRecords $records) use (&$seen): void {
            foreach ($records as $customer) {
                $seen[] = $customer->name;
            }
        });

        $table = $this->resolved($action, withProvider: true);

        $result = $this->runner()->run(
            $table,
            $action,
            new BulkSelection(allMatching: true, query: $this->dataTablesQuery()),
            $this->postRequest(),
        );

        $this->assertSame(['Alpha', 'Beta', 'Gamma', 'Delta'], $seen);
        $this->assertSame(4, $result->processed);
    }

    #[Test]
    public function it_forwards_the_displayed_request_to_the_provider_without_its_pagination(): void
    {
        $provider = new class implements DataProviderInterface, IdentifierCollectingDataProviderInterface {
            public ?DataTableRequest $seen = null;

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult([], 0, 0);
            }

            public function collectIdentifiers(DataTableRequest $request): array
            {
                $this->seen = $request;

                return [1];
            }
        };

        $action = BulkAction::new('touch')->handler(static function (BulkRecords $records): void {
            foreach ($records as $ignored) {
            }
        });

        $this->runner()->run(
            $this->resolved($action, provider: $provider),
            $action,
            new BulkSelection(allMatching: true, query: $this->dataTablesQuery(search: 'Beta')),
            $this->postRequest(),
        );

        $this->assertNotNull($provider->seen);
        $this->assertSame('Beta', $provider->seen->search?->value);
        // withoutPagination(): the batch covers the filtered set, not the page the user was on.
        $this->assertSame(0, $provider->seen->start);
        $this->assertSame(0, $provider->seen->length);
    }

    #[Test]
    public function it_subtracts_the_rows_deselected_after_a_select_all(): void
    {
        $seen   = [];
        $action = BulkAction::new('touch')->handler(function (BulkRecords $records) use (&$seen): void {
            foreach ($records as $customer) {
                $seen[] = $customer->name;
            }
        });

        $this->runner()->run(
            $this->resolved($action, withProvider: true),
            $action,
            new BulkSelection(allMatching: true, deselectedIds: ['2', 4], query: $this->dataTablesQuery()),
            $this->postRequest(),
        );

        $this->assertSame(['Alpha', 'Gamma'], $seen);
    }

    #[Test]
    public function it_refuses_a_select_all_on_a_table_restricted_to_the_current_page(): void
    {
        $action = BulkAction::new('touch')->handler(static fn () => null);

        $this->expectException(InvalidBulkSelectionException::class);
        $this->expectExceptionMessage('This table only allows selecting one page at a time.');

        $this->runner()->run(
            $this->resolved($action, withProvider: true, currentPageOnly: true),
            $action,
            new BulkSelection(allMatching: true, query: $this->dataTablesQuery()),
            $this->postRequest(),
        );
    }

    #[Test]
    public function it_refuses_a_select_all_on_a_provider_that_cannot_name_its_rows(): void
    {
        $action = BulkAction::new('touch')->handler(static fn () => null);

        $this->expectException(InvalidBulkSelectionException::class);
        $this->expectExceptionMessage('This table cannot resolve a selection across every matching row.');

        $this->runner()->run(
            $this->resolved($action),
            $action,
            new BulkSelection(allMatching: true, query: $this->dataTablesQuery()),
            $this->postRequest(),
        );
    }

    private function runner(
        ?AuthorizationChecker $permissionChecker = null,
        ?MutationFlusher $flusher = null,
        ?MercurePublisherInterface $publisher = null,
        ?MercureTopicResolver $topicResolver = null,
    ): BulkActionRunner {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        return new BulkActionRunner(
            new EntityLocator($registry),
            $permissionChecker ?? new AuthorizationChecker(),
            $flusher           ?? new MutationFlusher(),
            $publisher         ?? $this->createStub(MercurePublisherInterface::class),
            $topicResolver     ?? $this->createStub(MercureTopicResolver::class),
        );
    }

    private function resolved(
        BulkAction $action,
        bool $withProvider = false,
        bool $currentPageOnly = false,
        ?DataProviderInterface $provider = null,
    ): ResolvedDataTable {
        $table = new ConfigurableDataTable(
            columnsConfig: [TextColumn::new('id'), TextColumn::new('name')],
            configureTable: static fn (DataTable $table): DataTable => $table->serverSide(),
            dataProvider: $provider ?? ($withProvider ? new DoctrineDataProvider(
                em: $this->em,
                entityClass: CountCustomer::class,
                rowMapper: new class implements \Pentiminax\UX\DataTables\Contracts\RowMapperInterface {
                    public function map(mixed $row): array
                    {
                        $item = $row instanceof RowContext ? $row->item : $row;

                        return ['id' => $item->id, 'name' => $item->name];
                    }
                },
            ) : null),
            bulkActions: static fn (BulkActions $actions): BulkActions => $actions
                ->selectCurrentPageOnly($currentPageOnly)
                ->add($action),
        );

        return new ResolvedDataTable($table, CountCustomer::class, AbstractDataTable::class);
    }

    private function postRequest(): Request
    {
        return Request::create('/datatables/ajax/bulk', 'POST');
    }

    /**
     * @return array<string, mixed>
     */
    private function dataTablesQuery(string $search = ''): array
    {
        return [
            'draw'    => '1',
            'start'   => '0',
            'length'  => '2',
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ],
            'order'  => [['column' => '0', 'dir' => 'asc']],
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }
}
