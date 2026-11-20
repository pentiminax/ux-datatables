<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
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
 * Row and action permissions are evaluated on every render.
 *
 * @internal
 */
#[CoversClass(DataTableRuntime::class)]
final class DataTableRuntimeActionPermissionTest extends TestCase
{
    use BuildsRuntime;
    use PreparesPayload;

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

        $this->assertNull($this->rowActions($runtime, $table));
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
