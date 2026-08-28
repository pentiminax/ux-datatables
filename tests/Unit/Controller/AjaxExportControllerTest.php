<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Controller\AjaxExportController;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Export\ExporterRegistry;
use Pentiminax\UX\DataTables\Export\ExportService;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use Pentiminax\UX\DataTables\Tests\Support\RecordingExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[CoversClass(AjaxExportController::class)]
final class AjaxExportControllerTest extends TestCase
{
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

    private function createRegistry(?AbstractDataTable $table = null, string $class = 'App\\UserDataTable'): AjaxDataTableRegistry
    {
        $services = null === $table ? [] : ['app.user_datatable' => static fn (): AbstractDataTable => $table];
        $map      = null === $table ? [] : [$class => 'app.user_datatable'];

        return new AjaxDataTableRegistry(
            new ServiceLocator($services),
            new AjaxDataTableTokenManager('test-secret'),
            $map,
        );
    }

    private function exportTable(): ConfigurableDataTable
    {
        return new ConfigurableDataTable(
            [TextColumn::new('email')],
            extensions: static fn (DataTableExtensions $extensions): DataTableExtensions => $extensions->addExtension(
                new ButtonsExtension([Button::csv(serverSide: true)]),
            ),
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
