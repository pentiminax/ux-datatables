<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use Pentiminax\UX\DataTables\Tests\Support\PreparesPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * A container-shared table serves many requests under a worker runtime
 * (FrankenPHP worker mode, Swoole, RoadRunner). `resetDataTableState()` is what gives those
 * runtimes the same per-request isolation PHP-FPM gets for free.
 *
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableResetTest extends TestCase
{
    use BuildsRuntime;
    use PreparesPayload;

    #[Test]
    public function it_drops_the_rows_set_on_the_table_after_a_reset(): void
    {
        $runtime = self::buildRuntime();
        $table   = new ConfigurableDataTable([TextColumn::new('id')]);

        $table->rows([['id' => 1]]);

        $this->assertSame([['id' => 1]], self::optionOf($runtime->render($table), 'data'));

        $table->resetDataTableState();

        $this->assertSame([], self::optionOf($runtime->render($table), 'data'));
    }

    #[Test]
    public function it_renders_the_same_payload_twice_for_the_same_table(): void
    {
        $runtime = self::buildRuntime();
        $table   = new ConfigurableDataTable(
            [TextColumn::new('id')],
            configureTable: static fn (DataTable $table): DataTable => $table->data([['id' => 1]]),
        );

        $this->assertSame($runtime->render($table)->getView(), $runtime->render($table)->getView());
    }

    #[Test]
    public function it_maps_the_rows_at_render_time(): void
    {
        $runtime = self::buildRuntime();
        $table   = new ConfigurableDataTable([TextColumn::new('id')]);

        $table->rows([['id' => 1]]);
        $first = $runtime->render($table)->getView();

        $this->assertSame($first, $runtime->render($table)->getView());

        $table->rows([['id' => 2]]);

        $this->assertSame([['id' => 2]], self::optionOf($runtime->render($table), 'data'));
    }

    /**
     * The leak PR #323 reported: an action resolved for a privileged user keeps
     * its URL and CSRF token in the rows cached on the shared instance, and the
     * next render hands them to whoever asks.
     */
    #[Test]
    public function it_drops_action_data_resolved_for_the_previous_user_on_the_next_render(): void
    {
        $granted = true;

        [$runtime, $table] = $this->tableWithAction(
            Action::detail()
                ->permission('ROLE_ADMIN')
                ->linkToUrl(static fn (array $row): string => '/books/'.$row['id']),
            $granted,
        );

        $this->assertSame(
            ['DETAIL' => ['url' => '/books/5']],
            $this->rowActions($runtime, $table),
        );

        $granted = false;
        $table->resetDataTableState();

        $this->assertNull($this->rowActions($runtime, $table));
    }

    /**
     * Per-row permissions are re-evaluated too: intersecting the previous
     * request's action names would keep this one, since the action stays
     * configured and only its subject is denied.
     */
    #[Test]
    public function it_drops_per_row_denied_action_data_on_the_next_render(): void
    {
        $granted = true;

        [$runtime, $table] = $this->tableWithAction(
            Action::detail()
                ->permission('BOOK_VIEW', static fn (array $row): array => $row)
                ->linkToUrl(static fn (array $row): string => '/books/'.$row['id']),
            $granted,
        );

        $this->assertSame(
            ['DETAIL' => ['url' => '/books/5']],
            $this->rowActions($runtime, $table),
        );

        $granted = false;
        $table->resetDataTableState();

        $this->assertNull($this->rowActions($runtime, $table));
    }

    /**
     * `reset()` is a common enough method name on user tables (`ResetInterface`,
     * filter resets) that claiming it as a final method would break existing
     * subclasses on upgrade. Declaring one here fails at class-load time if the
     * hook is ever renamed back.
     */
    #[Test]
    public function it_leaves_the_reset_method_name_to_subclasses(): void
    {
        $table = new class extends AbstractDataTable {
            public bool $resetCalled = false;

            public function reset(): void
            {
                $this->resetCalled = true;
            }

            public function configureColumns(): iterable
            {
                return [TextColumn::new('id')];
            }
        };

        $table->resetDataTableState();

        $this->assertFalse($table->resetCalled);
    }

    /**
     * @return array{DataTableRuntime, ConfigurableDataTable}
     */
    private function tableWithAction(Action $action, bool &$granted): array
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker
            ->method('isGranted')
            ->willReturnCallback(static function () use (&$granted): bool {
                return $granted;
            });

        $table = new ConfigurableDataTable(
            [
                TextColumn::new('id'),
                ActionColumn::fromActions('actions', 'Actions', (new Actions())->add($action)),
            ],
            configureTable: static fn (DataTable $table): DataTable => $table->data([['id' => 5]]),
        );

        $permissionChecker = new AuthorizationChecker($checker);

        return [
            self::buildRuntime(
                checker: $permissionChecker,
                actionRowDataResolver: new ActionRowDataResolver($permissionChecker),
            ),
            $table,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rowActions(DataTableRuntime $runtime, ConfigurableDataTable $table): ?array
    {
        $data = self::optionOf($runtime->render($table), 'data');

        return $data[0][ActionRowDataResolver::ROW_ACTIONS_KEY] ?? null;
    }
}
