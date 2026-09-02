<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Export;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\StreamingDataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Export\ExporterRegistry;
use Pentiminax\UX\DataTables\Export\ExportService;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use Pentiminax\UX\DataTables\Tests\Support\RecordingExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @internal
 */
#[CoversClass(ExportService::class)]
final class ExportServiceTest extends TestCase
{
    #[Test]
    public function it_exports_exportable_columns_in_table_order(): void
    {
        $csv     = new RecordingExporter();
        $service = new ExportService(new ExporterRegistry([$csv]));

        $response = $service->export($this->table(), $this->exportRequest());
        $this->send($response);

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('users.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(['email', 'name'], array_map(
            static fn (ColumnInterface $column): string => $column->getName(),
            $csv->columns,
        ));
        $this->assertSame([
            ['email' => 'a@example.com', 'secret' => 'hidden', 'name' => 'Ada'],
            ['email' => 'b@example.com', 'secret' => 'hidden', 'name' => 'Bob'],
        ], $csv->rows);
    }

    #[Test]
    public function it_skips_hidden_columns(): void
    {
        $csv     = new RecordingExporter();
        $service = new ExportService(new ExporterRegistry([$csv]));

        $table = new ConfigurableDataTable(
            [TextColumn::new('email'), TextColumn::new('name')->setVisible(false)],
            extensions: static fn (DataTableExtensions $extensions): DataTableExtensions => $extensions->addExtension(
                new ButtonsExtension([Button::csv(serverSide: true)]),
            ),
            dataProvider: $this->provider(),
        );

        $this->send($service->export($table, $this->exportRequest()));

        $this->assertSame(['email'], array_map(
            static fn (ColumnInterface $column): string => $column->getName(),
            $csv->columns,
        ));
    }

    #[Test]
    public function it_selects_the_exporter_matching_the_requested_button(): void
    {
        $csv     = new RecordingExporter();
        $xlsx    = new RecordingExporter(ExportFormat::XLSX);
        $service = new ExportService(new ExporterRegistry([$csv, $xlsx]));

        $table = new ConfigurableDataTable(
            [TextColumn::new('email')],
            extensions: static fn (DataTableExtensions $extensions): DataTableExtensions => $extensions->addExtension(
                new ButtonsExtension([Button::csv(serverSide: true), Button::excel(serverSide: true)]),
            ),
            dataProvider: $this->provider(),
        );

        $response = $service->export($table, $this->exportRequest('xlsx'));
        $this->send($response);

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('configurable-data-table.xlsx', (string) $response->headers->get('Content-Disposition'));
        $this->assertCount(2, $xlsx->rows);
        $this->assertSame([], $csv->rows);
    }

    #[Test]
    public function it_falls_back_to_fetch_data_when_the_provider_is_not_streaming(): void
    {
        $csv   = new RecordingExporter();
        $table = new ConfigurableDataTable(
            [TextColumn::new('email')],
            extensions: static fn (DataTableExtensions $extensions): DataTableExtensions => $extensions->addExtension(
                new ButtonsExtension([Button::csv(serverSide: true)]),
            ),
            dataProvider: new class implements DataProviderInterface {
                public function fetchData(DataTableRequest $request): DataTableResult
                {
                    return new DataTableResult(1, 1, [
                        ['email' => 0 === $request->length ? 'all' : 'page'],
                    ]);
                }
            },
        );

        $this->send((new ExportService(new ExporterRegistry([$csv])))->export($table, $this->exportRequest()));

        $this->assertSame([['email' => 'all']], $csv->rows);
    }

    /**
     * Select's checkbox column is unshifted with name/data null. flattenFormValues used to
     * omit those keys, and Column::fromArray() TypeError'd before the download started.
     */
    #[Test]
    public function it_exports_when_the_payload_includes_a_checkbox_column_without_name_or_data(): void
    {
        $csv     = new RecordingExporter();
        $service = new ExportService(new ExporterRegistry([$csv]));

        $response = $service->export($this->table(), Request::create('/datatables/ajax/export', 'POST', [
            'draw'      => 4,
            'start'     => 0,
            'length'    => 0,
            'exportKey' => 'csv',
            'columns'   => [
                ['searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ]));
        $this->send($response);

        $this->assertCount(2, $csv->rows);
    }

    #[Test]
    public function it_exposes_forwarded_post_parameters_on_the_query_bag(): void
    {
        $table = new QueryScopedExportTable();

        $this->send($this->service()->export($table, Request::create('/datatables/ajax/export?table=token', 'POST', [
            'draw'      => 1,
            'start'     => 0,
            'length'    => 10,
            'exportKey' => 'csv',
            'pending'   => '1',
            'tenantIds' => ['1', '2'],
        ])));

        $this->assertSame('1', $table->pending);
        $this->assertSame(['1', '2'], $table->tenantIds);
    }

    #[Test]
    public function it_rejects_a_table_without_a_server_side_export_button(): void
    {
        $table = new ConfigurableDataTable(
            [TextColumn::new('email')],
            dataProvider: $this->provider(),
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('No server-side export button is configured on this table.');

        $this->service()->export($table, $this->exportRequest());
    }

    #[Test]
    public function it_rejects_an_unknown_export_key(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('No server-side export button is configured on this table.');

        $this->service()->export($this->table(), $this->exportRequest('nope'));
    }

    #[Test]
    public function it_rejects_an_invalid_datatables_payload(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid DataTables request.');

        $this->service()->export(
            $this->table(),
            Request::create('/datatables/ajax/export', 'POST'),
        );
    }

    #[Test]
    public function it_rejects_a_table_without_any_exportable_column(): void
    {
        $table = new ConfigurableDataTable(
            [TextColumn::new('secret')->setExportable(false)],
            extensions: static fn (DataTableExtensions $extensions): DataTableExtensions => $extensions->addExtension(
                new ButtonsExtension([Button::csv(serverSide: true)]),
            ),
            dataProvider: $this->provider(),
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('This table has no exportable column.');

        $this->service()->export($table, $this->exportRequest());
    }

    #[Test]
    public function it_rejects_a_table_without_a_data_provider(): void
    {
        $table = new ConfigurableDataTable(
            [TextColumn::new('email')],
            extensions: static fn (DataTableExtensions $extensions): DataTableExtensions => $extensions->addExtension(
                new ButtonsExtension([Button::csv(serverSide: true)]),
            ),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no data provider');

        $this->service()->export($table, $this->exportRequest());
    }

    #[Test]
    public function it_rejects_a_format_whose_writer_is_unavailable(): void
    {
        $service = new ExportService(new ExporterRegistry([new RecordingExporter(available: false)]));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('openspout/openspout');

        $service->export($this->table(), $this->exportRequest());
    }

    private function service(): ExportService
    {
        return new ExportService(new ExporterRegistry([new RecordingExporter()]));
    }

    private function send(StreamedResponse $response): void
    {
        ob_start();

        try {
            $response->sendContent();
        } finally {
            ob_end_clean();
        }
    }

    private function table(): ConfigurableDataTable
    {
        return new ConfigurableDataTable(
            [
                TextColumn::new('email'),
                TextColumn::new('secret')->setExportable(false),
                ActionColumn::fromActions('actions', 'Actions', new Actions()),
                TextColumn::new('name'),
            ],
            extensions: static fn (DataTableExtensions $extensions): DataTableExtensions => $extensions->addExtension(
                new ButtonsExtension([Button::csv(serverSide: true)->filename('users')]),
            ),
            dataProvider: $this->provider(),
        );
    }

    private function provider(): DataProviderInterface
    {
        return new ArrayDataProvider(
            [
                ['email' => 'a@example.com', 'secret' => 'hidden', 'name' => 'Ada'],
                ['email' => 'b@example.com', 'secret' => 'hidden', 'name' => 'Bob'],
            ],
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

    private function exportRequest(string $exportKey = 'csv'): Request
    {
        return Request::create('/datatables/ajax/export', 'POST', [
            'draw'      => 4,
            'start'     => 10,
            'length'    => 25,
            'exportKey' => $exportKey,
        ]);
    }
}

/**
 * Mirrors the documented customizeQueryBuilder() pattern that reads forwarded page
 * parameters from getHttpRequest()?->query, which Auto-Ajax GET puts on the query
 * string and the POST export endpoint puts in the body.
 *
 * @internal
 */
final class QueryScopedExportTable extends AbstractDataTable
{
    public ?string $pending = null;

    /**
     * @var list<string>|null
     */
    public ?array $tenantIds = null;

    public function configureColumns(): iterable
    {
        yield TextColumn::new('email');
    }

    public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
    {
        return $extensions->addExtension(new ButtonsExtension([Button::csv(serverSide: true)]));
    }

    protected function createDataProvider(): DataProviderInterface
    {
        return new class($this) implements DataProviderInterface, StreamingDataProviderInterface {
            public function __construct(private QueryScopedExportTable $table)
            {
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(1, 1, $this->iterateRows($request));
            }

            public function iterateRows(DataTableRequest $request): iterable
            {
                $this->table->capturePending();

                return [['email' => 'ada@example.com']];
            }
        };
    }

    public function capturePending(): void
    {
        $request = $this->getHttpRequest();
        $pending = $request?->query->get('pending');

        $this->pending   = \is_string($pending) ? $pending : null;
        $this->tenantIds = array_values(array_filter($request?->query->all('tenantIds') ?? [], \is_string(...)));
    }
}
