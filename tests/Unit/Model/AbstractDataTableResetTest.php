<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * A container-shared table serves many requests under a worker runtime
 * (FrankenPHP worker mode, Swoole, RoadRunner). `reset()` is what gives those
 * runtimes the same per-request isolation PHP-FPM gets for free.
 *
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableResetTest extends TestCase
{
    #[Test]
    public function it_re_evaluates_the_configuration_after_a_reset(): void
    {
        $rows = [['id' => 1]];

        $table = new ConfigurableDataTable(
            [TextColumn::new('id')],
            configureTable: static function (DataTable $table) use (&$rows): DataTable {
                return $table->data($rows);
            },
        );
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault());

        $this->assertSame([['id' => 1]], $table->getDataTable()->getOption('data'));

        $rows = [['id' => 2]];

        $this->assertSame(
            [['id' => 1]],
            $table->getDataTable()->getOption('data'),
            'Without a reset the first request\'s rows stay frozen on the shared instance.',
        );

        $table->reset();

        $this->assertSame([['id' => 2]], $table->getDataTable()->getOption('data'));
    }

    #[Test]
    public function it_rebuilds_the_data_table_after_a_reset(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault());

        $first = $table->getDataTable();

        $table->reset();

        $this->assertNotSame($first, $table->getDataTable());
    }

    #[Test]
    public function it_drops_the_handled_request_after_a_reset(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault());

        $table->handleRequest(new Request(['draw' => '1']));

        $this->assertNotNull($table->getRequest());

        $table->reset();

        $this->assertNull($table->getRequest());
        $this->assertFalse($table->isRequestHandled());
    }

    /**
     * The leak PR #323 reported: an action resolved for a privileged user keeps
     * its URL and CSRF token in the rows cached on the shared instance, and the
     * next render hands them to whoever asks.
     */
    #[Test]
    public function it_drops_action_data_resolved_for_the_previous_user_after_a_reset(): void
    {
        $granted = true;

        $table = $this->tableWithAction(
            Action::detail()
                ->permission('ROLE_ADMIN')
                ->linkToUrl(static fn (array $row): string => '/books/'.$row['id']),
            $granted,
        );

        $this->assertSame(
            ['DETAIL' => ['url' => '/books/5']],
            $this->rowActions($table),
        );

        $granted = false;
        $table->reset();

        $this->assertNull($this->rowActions($table));
    }

    /**
     * Per-row permissions are re-evaluated too: intersecting the previous
     * request's action names would keep this one, since the action stays
     * configured and only its subject is denied.
     */
    #[Test]
    public function it_drops_per_row_denied_action_data_after_a_reset(): void
    {
        $granted = true;

        $table = $this->tableWithAction(
            Action::detail()
                ->permission('BOOK_VIEW', static fn (array $row): array => $row)
                ->linkToUrl(static fn (array $row): string => '/books/'.$row['id']),
            $granted,
        );

        $this->assertSame(
            ['DETAIL' => ['url' => '/books/5']],
            $this->rowActions($table),
        );

        $granted = false;
        $table->reset();

        $this->assertNull($this->rowActions($table));
    }

    #[Test]
    public function it_accepts_infrastructure_injection_after_a_reset(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault());

        $table->getDataTable();
        $table->reset();

        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault());

        $this->assertNotNull($table->getDataTable());
    }

    private function tableWithAction(Action $action, bool &$granted): ConfigurableDataTable
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker
            ->method('isGranted')
            ->willReturnCallback(static function () use (&$granted): bool {
                return $granted;
            });

        $permissionChecker = new PermissionChecker($checker);

        $table = new ConfigurableDataTable(
            [
                TextColumn::new('id'),
                ActionColumn::fromActions('actions', 'Actions', (new Actions())->add($action)),
            ],
            configureTable: static fn (DataTable $table): DataTable => $table->data([['id' => 5]]),
        );

        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            columnResolver: new ColumnResolver(permissionChecker: $permissionChecker),
            runtimeFactory: new DataTableRuntimeFactory(
                actionRowDataResolver: new ActionRowDataResolver($permissionChecker),
                permissionChecker: $permissionChecker,
            ),
        ));

        return $table;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rowActions(ConfigurableDataTable $table): ?array
    {
        $data = $table->getDataTable()->getOption('data');

        return $data[0][ActionRowDataResolver::ROW_ACTIONS_KEY] ?? null;
    }
}
