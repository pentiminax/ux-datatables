<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Controller\AjaxExportController;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Export\ExporterRegistry;
use Pentiminax\UX\DataTables\Export\ExportService;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsAjaxRegistry;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use Pentiminax\UX\DataTables\Tests\Support\RecordingExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @internal
 */
#[CoversClass(AjaxExportController::class)]
final class AjaxExportControllerTest extends TestCase
{
    use BuildsAjaxRegistry;

    #[Test]
    public function it_throws_404_when_the_table_field_is_missing(): void
    {
        $controller = new AjaxExportController($this->createRegistry(), $this->unusedExportService());

        $this->expectException(NotFoundHttpException::class);

        $controller(new Request());
    }

    #[Test]
    public function it_throws_404_when_the_table_token_is_unknown(): void
    {
        $controller = new AjaxExportController($this->createRegistry(), $this->unusedExportService());

        $this->expectException(NotFoundHttpException::class);

        $controller(new Request(query: ['table' => 'unknown-token']));
    }

    #[Test]
    public function it_dispatches_to_the_export_service(): void
    {
        $exporter = new RecordingExporter();

        $table    = $this->exportTable();
        $registry = $this->createRegistry($table, $table::class);
        $token    = $registry->getToken($table::class);

        $controller = new AjaxExportController($registry, new ExportService(new ExporterRegistry([$exporter])));
        $response   = $controller(new Request(
            query: ['table' => $token],
            request: ['draw' => 1, 'start' => 0, 'length' => 10, 'exportKey' => 'csv'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));

        ob_start();
        $response->sendContent();
        ob_end_clean();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['email'], array_map(
            static fn (ColumnInterface $column): string => $column->getName(),
            $exporter->columns,
        ));
    }

    #[Test]
    public function it_denies_the_export_when_access_to_the_table_is_refused(): void
    {
        $exporter = new RecordingExporter();

        $table    = $this->exportTable();
        $registry = $this->createRegistry($table, $table::class, $this->denyingPermissionChecker());
        $token    = $registry->getToken($table::class);

        $controller = new AjaxExportController($registry, new ExportService(new ExporterRegistry([$exporter])));

        try {
            $controller(new Request(
                query: ['table' => $token],
                request: ['draw' => 1, 'start' => 0, 'length' => 10, 'exportKey' => 'csv'],
                server: ['REQUEST_METHOD' => 'POST'],
            ));

            $this->fail('The export controller should refuse a table the user may not access.');
        } catch (AccessDeniedException) {
        }

        $this->assertSame([], $exporter->columns);
        $this->assertSame([], $exporter->rows);
    }

    #[Test]
    public function it_refuses_an_action_token_replayed_on_the_read_route(): void
    {
        $exporter = new RecordingExporter();

        $table    = $this->exportTable();
        $registry = $this->createRegistry($table, $table::class);

        $controller = new AjaxExportController($registry, new ExportService(new ExporterRegistry([$exporter])));

        $this->expectException(NotFoundHttpException::class);

        try {
            $controller(new Request(
                query: ['table' => $registry->getActionToken($table::class)],
                request: ['draw' => 1, 'start' => 0, 'length' => 10, 'exportKey' => 'csv'],
                server: ['REQUEST_METHOD' => 'POST'],
            ));
        } finally {
            $this->assertSame([], $exporter->rows);
        }
    }

    private function createRegistry(
        ?AbstractDataTable $table = null,
        string $class = 'App\\UserDataTable',
        ?AuthorizationChecker $permissionChecker = null,
    ): AjaxDataTableRegistry {
        return $this->createAjaxRegistry(
            null === $table ? [] : [$class => 'app.user_datatable'],
            null === $table ? [] : ['app.user_datatable' => $table],
            $permissionChecker,
        );
    }

    private function denyingPermissionChecker(): AuthorizationChecker
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        return new AuthorizationChecker($checker);
    }

    private function exportTable(): ConfigurableDataTable
    {
        return new ConfigurableDataTable(
            [TextColumn::new('email')],
            configureTable: static fn (DataTable $table): DataTable => $table->buttons([
                Button::csv(serverSide: true),
            ]),
            dataProvider: $this->provider(),
        );
    }

    private function provider(): DataProviderInterface
    {
        return new ArrayDataProvider(
            [['email' => 'ada@example.com']],
            new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    if (\is_array($row)) {
                        return $row;
                    }

                    return \is_object($row) ? (array) $row : [];
                }
            },
        );
    }

    private function unusedExportService(): ExportService
    {
        return new ExportService(new ExporterRegistry([
            new RecordingExporter(failure: new \LogicException('Export service should not run.')),
        ]));
    }
}
