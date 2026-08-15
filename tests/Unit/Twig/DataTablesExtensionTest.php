<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Twig;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoAjaxServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @internal
 */
#[CoversClass(DataTablesExtension::class)]
final class DataTablesExtensionTest extends TestCase
{
    private TwigAppKernel $kernel;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->kernel = new TwigAppKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer()->get('test.service_container');
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    #[Test]
    public function it_renders_datatable(): void
    {
        $table = $this->builder()
            ->createDataTable('table')
            ->lengthMenu([10, 25, 50, 100])
            ->pageLength(25)
        ;

        $table->columns([
            TextColumn::new('firstColumn'),
            TextColumn::new('secondColumn'),
        ]);

        $table->data([
            ['firstColumn' => 'Row 1 Column 1', 'secondColumn' => 'Row 1 Column 2'],
            ['firstColumn' => 'Row 2 Column 1', 'secondColumn' => 'Row 2 Column 2'],
        ]);

        $tableEl = $this->renderTableElement($table, ['data-controller' => 'mycontroller', 'class' => 'myclass']);

        $this->assertSame('table', $tableEl->getAttribute('id'));
        $this->assertSame('mycontroller pentiminax--ux-datatables--datatable', $tableEl->getAttribute('data-controller'));
        $this->assertSame('myclass', $tableEl->getAttribute('class'));

        $expected = [
            'lengthMenu' => [10, 25, 50, 100],
            'pageLength' => 25,
            'columns'    => [
                [
                    'data'       => 'firstColumn',
                    'name'       => 'firstColumn',
                    'orderable'  => true,
                    'searchable' => true,
                    'title'      => 'firstColumn',
                    'type'       => 'string',
                    'visible'    => true,
                    'field'      => 'firstColumn',
                ],
                [
                    'data'       => 'secondColumn',
                    'name'       => 'secondColumn',
                    'orderable'  => true,
                    'searchable' => true,
                    'title'      => 'secondColumn',
                    'type'       => 'string',
                    'visible'    => true,
                    'field'      => 'secondColumn',
                ],
            ],
            'data' => [
                ['firstColumn' => 'Row 1 Column 1', 'secondColumn' => 'Row 1 Column 2'],
                ['firstColumn' => 'Row 2 Column 1', 'secondColumn' => 'Row 2 Column 2'],
            ],
            'dataTableClass' => null,
            'dataTable'      => null,
            'editModal'      => [
                'adapter' => null,
            ],
            'mutationsEnabled' => false,
        ];

        $this->assertSame($expected, $this->decodePayload($tableEl));
    }

    #[Test]
    public function it_exposes_edit_modal_overrides_and_the_datatable_class(): void
    {
        $table = new ConfigurableDataTable(
            [TextColumn::new('firstColumn')],
            configureTable: static fn (DataTable $table): DataTable => $table
                ->editModalTemplate('custom/modal.html.twig')
                ->editModalAdapter('tw'),
        );

        $actual = $this->renderPayload($table);

        $this->assertSame('tw', $actual['editModal']['adapter']);
        $this->assertSame($table::class, $actual['dataTableClass']);
        $this->assertNull($actual['dataTable']);
    }

    #[Test]
    public function it_exposes_an_opaque_datatable_token_for_registered_abstract_datatables(): void
    {
        /** @var AbstractDataTable $table */
        $table = $this->container->get('test.datatables.auto_ajax_server_side');

        $actual = $this->renderPayload($table);

        $this->assertSame($table::class, $actual['dataTableClass']);
        $this->assertIsString($actual['dataTable']);
        $this->assertNotSame('', $actual['dataTable']);
        $this->assertStringNotContainsString('AutoAjaxServerSideDataTable', $actual['dataTable']);
    }

    #[Test]
    public function it_exposes_a_boolean_mutation_token_for_a_raw_datatable_with_a_registered_class(): void
    {
        $table = (new DataTable('products'))
            ->setDataTableClass(AutoAjaxServerSideDataTable::class);

        $actual    = $this->renderPayload($table);
        $ajaxToken = $this->container->get('datatables.ajax.registry')
            ->getToken(AutoAjaxServerSideDataTable::class);

        $this->assertSame(AutoAjaxServerSideDataTable::class, $actual['dataTableClass']);
        $this->assertIsString($actual['dataTable']);
        $this->assertNotSame('', $actual['dataTable']);
        $this->assertNotSame($ajaxToken, $actual['dataTable']);
    }

    #[Test]
    public function it_exposes_the_current_request_locale_and_enables_mutations_from_the_session(): void
    {
        $request = Request::create('/products');
        $request->setLocale('fr_FR');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->container->get('request_stack')->push($request);

        $actual = $this->renderPayload((new DataTable('products'))->columns([TextColumn::new('name')]));

        $this->assertSame('fr_FR', $actual['locale']);
        $this->assertTrue($actual['mutationsEnabled']);
        $this->assertNotSame('', $actual['csrfToken']);
    }

    #[Test]
    public function it_uses_get_data_table_for_abstract_datatable(): void
    {
        $table = new class extends AbstractDataTable {
            public bool $getDataTableCalled        = false;
            public bool $prepareForRenderingCalled = false;

            public function configureColumns(): iterable
            {
                yield TextColumn::new('firstColumn');
            }

            public function getDataTable(): DataTable
            {
                $this->getDataTableCalled = true;

                return parent::getDataTable();
            }

            public function prepareForRendering(): void
            {
                $this->prepareForRenderingCalled = true;

                parent::prepareForRendering();
            }
        };

        $this->container->get('test.datatables.twig_extension')->renderDataTable($table);

        $this->assertTrue($table->getDataTableCalled);
        $this->assertTrue($table->prepareForRenderingCalled);
    }

    /**
     * @param array<string, mixed>|null $expectedRow the expected first inline row, or null when no inline data is expected
     */
    #[Test]
    #[DataProvider('inlineRenderingCases')]
    public function it_prepares_inline_rows_during_rendering(string $mode, ?array $expectedRow): void
    {
        $table = $this->createInlineTable($mode);

        $actual = $this->renderPayload($table);

        if (null === $expectedRow) {
            $this->assertArrayNotHasKey('data', $actual);
            $this->assertFalse($table->areTemplateColumnsRendered());

            return;
        }

        $this->assertSame($expectedRow, $this->trimRow($actual['data'][0]));
        $this->assertTrue($table->areTemplateColumnsRendered());
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>|null}>
     */
    public static function inlineRenderingCases(): iterable
    {
        yield 'template columns are pre-rendered' => ['template', [
            'id'             => 5,
            'status'         => 'active',
            'status_display' => '<span class="badge">5-active</span>',
        ]];

        yield 'template columns already marked as rendered are left untouched' => ['marked', [
            'id'     => 5,
            'status' => 'active',
        ]];

        yield 'ajax tables carry no inline data' => ['ajax', null];

        yield 'detail action urls are resolved' => ['actions', [
            'id'                      => 5,
            '__ux_datatables_actions' => ['DETAIL' => ['url' => '/books/5']],
        ]];
    }

    /**
     * @param array<string, mixed> $expectedRow
     */
    #[Test]
    #[DataProvider('inlineObjectRowCases')]
    public function it_prepares_inline_object_rows_during_rendering(string $mode, array $expectedRow): void
    {
        $table = $this->createInlineObjectTable($mode);

        $actual = $this->renderPayload($table);

        $this->assertSame($expectedRow, $this->trimRow($actual['data'][0]));
        $this->assertTrue($table->getDataTable()->areTemplateColumnsRendered());
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function inlineObjectRowCases(): iterable
    {
        yield 'rows passed to setData()' => ['setData', [
            'id'                      => 5,
            'title'                   => 'Dune',
            'status_display'          => '<span class="badge">5-active</span>',
            '__ux_datatables_actions' => ['DETAIL' => ['url' => '/books/5']],
        ]];

        yield 'rows configured through data()' => ['configured', [
            'id'                      => 5,
            'title'                   => 'Dune',
            'status_display'          => '<span class="badge">dto-5-active</span>',
            '__ux_datatables_actions' => ['DETAIL' => ['url' => '/books/5']],
        ]];
    }

    #[Test]
    public function it_keeps_set_data_rows_prepared_before_rendering(): void
    {
        $table = $this->createInlineObjectTable('setData');

        $this->assertTrue($table->getDataTable()->areTemplateColumnsRendered());
    }

    #[Test]
    public function it_filters_denied_columns_on_direct_datatable_rendering(): void
    {
        $table = $this->builder()->createDataTable('permission_table');
        $table->columns([
            TextColumn::new('id'),
            TextColumn::new('secret')->permission('ROLE_DENIED'),
        ]);
        $table->data([
            ['id' => 5, 'secret' => 'hidden'],
        ]);

        $actual = $this->renderPayload($table);

        $this->assertSame(['id'], array_column($actual['columns'], 'name'));
    }

    #[Test]
    public function it_filters_denied_static_actions_on_direct_datatable_rendering(): void
    {
        $actions = (new Actions())->add(
            Action::detail()
                ->permission('ROLE_DENIED')
                ->linkToUrl(static fn (array $row): string => '/books/'.$row['id'])
        );

        $table = $this->builder()->createDataTable('denied_actions_table');
        $table->columns([
            TextColumn::new('id'),
            ActionColumn::fromActions('actions', 'Actions', $actions),
        ]);
        $table->data([
            ['id' => 5],
        ]);

        $actual = $this->renderPayload($table);

        $actionColumn = array_values(array_filter(
            $actual['columns'],
            static fn (array $column): bool => 'actions' === $column['name'],
        ))[0];

        $this->assertSame([], $actionColumn['actions']);
        $this->assertArrayNotHasKey('__ux_datatables_actions', $actual['data'][0]);
    }

    /**
     * @param list<array<string, mixed>>|null $expectedData
     */
    #[Test]
    #[DataProvider('autoHydrationCases')]
    public function it_auto_hydrates_client_side_abstract_datatables_without_an_explicit_data_source(
        string $mode,
        int $expectedProviderCalls,
        ?array $expectedData,
    ): void {
        $table = new ProviderHydratedDataTable([
            new InlineBook(id: 5, title: 'Dune', status: 'active'),
            new InlineBook(id: 9, title: 'Foundation', status: 'active'),
        ], $mode);

        $actual = $this->renderPayload($table);

        $this->assertSame($expectedProviderCalls, $table->providerCalls);

        if (null === $expectedData) {
            $this->assertArrayNotHasKey('data', $actual);

            return;
        }

        $this->assertSame($expectedData, $actual['data']);
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: list<array<string, mixed>>|null}>
     */
    public static function autoHydrationCases(): iterable
    {
        yield 'client side table without data source' => ['default', 1, [
            [
                'id'                      => 5,
                'title'                   => 'Dune',
                '__ux_datatables_actions' => ['DETAIL' => ['url' => '/books/5']],
            ],
            [
                'id'                      => 9,
                'title'                   => 'Foundation',
                '__ux_datatables_actions' => ['DETAIL' => ['url' => '/books/9']],
            ],
        ]];

        yield 'server side table' => ['serverSide', 0, null];

        yield 'ajax table' => ['ajax', 0, null];

        yield 'api platform table' => ['apiPlatform', 0, null];

        yield 'table with explicit data' => ['data', 0, [['id' => 99, 'title' => 'Manual']]];
    }

    #[Test]
    public function it_renders_template_columns_in_service_managed_server_side_ajax_response(): void
    {
        /** @var AbstractDataTable $table */
        $table = $this->container->get('test.datatables.server_side_template');
        $table->handleRequest(Request::create('/datatable/books', 'GET', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'search'  => ['value' => '', 'regex' => 'false'],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'status', 'name' => 'status_display', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ]));

        $payload = json_decode($table->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('<span class="badge">7-active</span>', trim($payload['data'][0]['status_display']));
    }

    #[Test]
    public function it_dispatches_auto_ajax_for_service_managed_server_side_table_with_custom_service_id(): void
    {
        $token = $this->container->get('datatables.ajax.registry')->getToken(AutoAjaxServerSideDataTable::class);

        $this->assertIsString($token);
        $this->assertStringNotContainsString('AutoAjaxServerSideDataTable', $token);

        $response = $this->container->get('datatables.controller.ajax_data')(Request::create('/datatables/ajax/data', 'GET', [
            'table'   => $token,
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'search'  => ['value' => '', 'regex' => 'false'],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ]));

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $payload['draw']);
        $this->assertSame('Generated endpoint', $payload['data'][0]['title']);
    }

    #[Test]
    public function it_rejects_an_auto_ajax_request_without_a_valid_datatables_payload(): void
    {
        $token      = $this->container->get('datatables.ajax.registry')->getToken(AutoAjaxServerSideDataTable::class);
        $controller = $this->container->get('datatables.controller.ajax_data');

        $this->expectException(BadRequestHttpException::class);

        $controller(Request::create('/datatables/ajax/data', 'GET', ['table' => $token]));
    }

    private function createInlineTable(string $mode): DataTable
    {
        $table = $this->builder()->createDataTable('inline_table');

        if ('actions' === $mode) {
            $actions = (new Actions())->add(
                Action::detail()
                    ->setClassName('btn btn-info')
                    ->linkToUrl(static fn (array $row): string => '/books/'.$row['id'])
            );

            $table->columns([
                TextColumn::new('id'),
                ActionColumn::fromActions('actions', 'Actions', $actions),
            ]);
            $table->data([['id' => 5]]);

            return $table;
        }

        $table->columns([
            TextColumn::new('id'),
            TemplateColumn::new('status_display')
                ->setField('status')
                ->setTemplate('datatable/columns/status_badge.html.twig'),
        ]);

        if ('ajax' === $mode) {
            $table->ajax('/api/items');

            return $table;
        }

        $table->data([['id' => 5, 'status' => 'active']]);

        if ('marked' === $mode) {
            $table->markTemplateColumnsRendered();
        }

        return $table;
    }

    private function createInlineObjectTable(string $mode): AbstractDataTable
    {
        $rows     = [new InlineBook(id: 5, title: 'Dune', status: 'active')];
        $template = 'setData' === $mode
            ? 'datatable/columns/status_badge.html.twig'
            : 'datatable/columns/entity_status_badge.html.twig';

        $table = new ConfigurableDataTable(
            [
                TextColumn::new('id'),
                TextColumn::new('title'),
                TemplateColumn::new('status_display')
                    ->setField('status')
                    ->setTemplate($template),
            ],
            actions: static fn (Actions $actions): Actions => $actions->add(
                Action::detail()->linkToUrl(static fn (InlineBook $book): string => '/books/'.$book->getId())
            ),
            configureTable: 'setData' === $mode
                ? null
                : static fn (DataTable $table): DataTable => $table->data($rows),
        );

        $table->setDataTableInfrastructure($this->container->get('test.datatables.infrastructure'));

        if ('setData' === $mode) {
            $table->setData($rows);
        }

        return $table;
    }

    private function builder(): DataTableBuilderInterface
    {
        return $this->container->get('test.datatables.builder');
    }

    /**
     * @return array<string, mixed>
     */
    private function renderPayload(AbstractDataTable|DataTable $table): array
    {
        return $this->decodePayload($this->renderTableElement($table));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function renderTableElement(AbstractDataTable|DataTable $table, array $attributes = []): \DOMElement
    {
        $rendered = $this->container->get('test.datatables.twig_extension')->renderDataTable($table, $attributes);

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);

        return $dom->getElementsByTagName('table')->item(0);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(\DOMElement $tableEl): array
    {
        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));

        return json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function trimRow(array $row): array
    {
        return array_map(static fn (mixed $value): mixed => \is_string($value) ? trim($value) : $value, $row);
    }
}

final readonly class InlineBook
{
    public function __construct(
        private int $id,
        private string $title,
        private string $status,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTemplateLabel(): string
    {
        return 'dto-'.$this->id;
    }
}

final class ProviderHydratedDataTable extends AbstractDataTable
{
    public int $providerCalls = 0;

    /**
     * @param list<InlineBook> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $mode = 'default',
    ) {
        parent::__construct();
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return match ($this->mode) {
            'serverSide'  => $table->serverSide(),
            'ajax'        => $table->ajax('/books.json'),
            'data'        => $table->data([['id' => 99, 'title' => 'Manual']]),
            'apiPlatform' => $table->apiPlatform(),
            default       => $table,
        };
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TextColumn::new('title');
    }

    public function configureActions(Actions $actions): Actions
    {
        if ('data' === $this->mode) {
            return $actions;
        }

        return $actions->add(
            Action::detail()->linkToUrl(static fn (InlineBook $book): string => '/books/'.$book->getId())
        );
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        ++$this->providerCalls;

        return new ArrayDataProvider($this->items, $this->createRowMapper());
    }
}
