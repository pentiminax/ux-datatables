<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Twig;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoAjaxServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Support\BootsTwigKernel;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use Pentiminax\UX\DataTables\Twig\DataTablesRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;

/**
 * @internal
 */
#[CoversClass(DataTablesExtension::class)]
#[CoversClass(DataTablesRuntime::class)]
final class DataTablesExtensionTest extends TestCase
{
    use BootsTwigKernel;
    use BuildsRuntime;
    use CollectsDeprecations;

    #[Test]
    public function it_renders_the_datatable_controller(): void
    {
        $tableEl = $this->renderTableElement($this->inlineTable([TextColumn::new('name')]));

        $this->assertSame('pentiminax--ux-datatables--datatable', $tableEl->getAttribute('data-controller'));
        $this->assertTrue($tableEl->hasAttribute(DataTablesExtension::VIEW_ATTRIBUTE));
    }

    #[Test]
    public function it_renders_datatable(): void
    {
        $table = $this->inlineTable(
            [
                TextColumn::new('firstColumn'),
                TextColumn::new('secondColumn'),
            ],
            static fn (DataTable $table): DataTable => $table
                ->lengthMenu([10, 25, 50, 100])
                ->pageLength(25)
                ->data([
                    ['firstColumn' => 'Row 1 Column 1', 'secondColumn' => 'Row 1 Column 2'],
                    ['firstColumn' => 'Row 2 Column 1', 'secondColumn' => 'Row 2 Column 2'],
                ]),
        );

        $tableEl = $this->renderTableElement($table, ['data-controller' => 'mycontroller', 'class' => 'myclass']);

        $this->assertSame('ConfigurableDataTable', $tableEl->getAttribute('id'));
        $this->assertSame('mycontroller pentiminax--ux-datatables--datatable', $tableEl->getAttribute('data-controller'));
        $this->assertSame('myclass', $tableEl->getAttribute('class'));

        $expected = [
            'lengthMenu' => [10, 25, 50, 100],
            'pageLength' => 25,
            'data'       => [
                ['firstColumn' => 'Row 1 Column 1', 'secondColumn' => 'Row 1 Column 2'],
                ['firstColumn' => 'Row 2 Column 1', 'secondColumn' => 'Row 2 Column 2'],
            ],
            'columns' => [
                [
                    'className'  => 'dt-exportable',
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
                    'className'  => 'dt-exportable',
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
            'dataTable' => null,
            'editModal' => [
                'adapter' => null,
            ],
            'mutationsEnabled' => false,
        ];

        $this->assertSame($expected, $this->decodePayload($tableEl));
    }

    #[Test]
    public function it_replaces_the_default_id_with_a_custom_one_exactly_once(): void
    {
        $table = $this->inlineTable([TextColumn::new('name')]);

        $rendered = $this->container->get('test.datatables.twig_runtime')->renderDataTable($table, ['id' => 'orders-open']);

        $this->assertSame(1, substr_count($rendered, ' id="'));
        $this->assertStringStartsWith('<table id="orders-open" ', $rendered);
    }

    #[Test]
    public function it_renders_the_same_table_twice_with_different_ids_without_leaking_attributes(): void
    {
        $table = $this->inlineTable([TextColumn::new('name')]);

        $first  = $this->renderTableElement($table, ['id' => 'orders-open', 'class' => 'first']);
        $second = $this->renderTableElement($table, ['id' => 'orders-closed']);
        $third  = $this->renderTableElement($table);

        $this->assertSame('orders-open', $first->getAttribute('id'));
        $this->assertSame('first', $first->getAttribute('class'));
        $this->assertSame('orders-closed', $second->getAttribute('id'));
        $this->assertSame('', $second->getAttribute('class'));
        $this->assertSame('ConfigurableDataTable', $third->getAttribute('id'));
    }

    #[Test]
    public function it_escapes_a_custom_id(): void
    {
        $table = $this->inlineTable([TextColumn::new('name')]);

        $rendered = $this->container->get('test.datatables.twig_runtime')->renderDataTable($table, ['id' => 'a"b<c']);

        $this->assertStringStartsWith('<table id="a&quot;b&lt;c" ', $rendered);
    }

    #[Test]
    public function it_exposes_edit_modal_overrides(): void
    {
        $table = new ConfigurableDataTable(
            [TextColumn::new('firstColumn')],
            configureTable: static fn (DataTable $table): DataTable => $table
                ->editModalTemplate('custom/modal.html.twig')
                ->editModalAdapter('tw'),
        );

        $actual = $this->renderPayload($table);

        $this->assertSame('tw', $actual['editModal']['adapter']);
        $this->assertNull($actual['dataTable']);
    }

    #[Test]
    public function it_exposes_an_opaque_datatable_token_for_registered_abstract_datatables(): void
    {
        /** @var AbstractDataTable $table */
        $table = $this->container->get('test.datatables.auto_ajax_server_side');

        $actual    = $this->renderPayload($table);
        $ajaxToken = $this->container->get('datatables.ajax.registry')
            ->getToken(AutoAjaxServerSideDataTable::class);

        $this->assertIsString($actual['dataTable']);
        $this->assertNotSame('', $actual['dataTable']);
        $this->assertStringNotContainsString('AutoAjaxServerSideDataTable', $actual['dataTable']);
        $this->assertNotSame($ajaxToken, $actual['dataTable']);
    }

    #[Test]
    public function it_rejects_denied_table_access_before_hydrating_rows(): void
    {
        $provider = $this->createMock(DataProviderInterface::class);
        $provider->expects($this->never())->method('fetchData');

        $table = new ConfigurableDataTable(
            [TextColumn::new('id')],
            configureTable: static fn (DataTable $table): DataTable => $table->permission('ROLE_ADMIN'),
            dataProvider: $provider,
        );

        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->expects($this->once())
            ->method('isGranted')
            ->with(Permission::DT_ACCESS_TABLE, $table)
            ->willReturn(false);

        $checker = new AuthorizationChecker($inner);

        $extension = new DataTablesRuntime(
            new StimulusHelper(null),
            $checker,
            self::buildRuntime(checker: $checker),
        );

        $this->expectException(AccessDeniedException::class);
        $extension->renderDataTable($table);
    }

    #[Test]
    public function it_exposes_the_current_request_locale_and_enables_mutations_from_the_session(): void
    {
        $request = Request::create('/products');
        $request->setLocale('fr_FR');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->container->get('request_stack')->push($request);

        $actual = $this->renderPayload($this->inlineTable([TextColumn::new('name')]));

        $this->assertSame('fr_FR', $actual['locale']);
        $this->assertTrue($actual['mutationsEnabled']);
        $this->assertNotSame('', $actual['csrfToken']);
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

            return;
        }

        $this->assertSame($expectedRow, $this->trimRow($actual['data'][0]));
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
        $actual = $this->renderInlineObjectPayload($mode);

        $this->assertSame($expectedRow, $this->trimRow($actual['data'][0]));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function inlineObjectRowCases(): iterable
    {
        yield 'rows passed as the render argument' => ['argument', [
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
    public function it_gives_each_render_its_own_rows(): void
    {
        $table   = $this->createInlineObjectTable('argument');
        $runtime = $this->container->get('test.datatables.twig_runtime');

        $first  = $this->payloadOf($runtime->renderDataTable($table, rows: [new InlineBook(id: 5, title: 'Dune', status: 'active')]));
        $second = $this->payloadOf($runtime->renderDataTable($table, rows: [new InlineBook(id: 6, title: 'Emma', status: 'active')]));

        $this->assertSame([5], array_column($first['data'], 'id'));
        $this->assertSame([6], array_column($second['data'], 'id'));
    }

    #[Test]
    public function it_renders_an_empty_array_as_an_empty_inline_table(): void
    {
        $table = $this->createInlineObjectTable('configured');

        $payload = $this->payloadOf($this->container->get('test.datatables.twig_runtime')->renderDataTable($table, rows: []));

        $this->assertSame([], $payload['data']);
    }

    /**
     * @param array<string, mixed> $expectedRow
     */
    #[Test]
    #[DataProvider('inlineObjectRowCases')]
    public function it_renders_the_same_payload_twice(string $mode, array $expectedRow): void
    {
        $this->assertSame($this->renderInlineObjectPayload($mode), $this->renderInlineObjectPayload($mode));
        $this->assertSame($expectedRow, $this->trimRow($this->renderInlineObjectPayload($mode)['data'][0]));
    }

    #[Test]
    public function it_strips_denied_nested_dotted_paths_from_embedded_rows(): void
    {
        $table = $this->inlineTable(
            [
                TextColumn::new('name'),
                TextColumn::new('user.email')->permission('ROLE_DENIED'),
            ],
            static fn (DataTable $table): DataTable => $table->data([
                ['name' => 'Ada', 'user' => ['email' => 'secret', 'role' => 'admin']],
            ]),
        );

        $actual = $this->renderPayload($table);

        $this->assertSame(['name'], array_column($actual['columns'], 'name'));
        $this->assertSame(
            ['name' => 'Ada', 'user' => ['role' => 'admin']],
            $actual['data'][0],
        );
    }

    #[Test]
    public function it_strips_denied_nested_paths_from_object_backed_embedded_rows(): void
    {
        $table = $this->inlineTable(
            [
                TextColumn::new('name'),
                TextColumn::new('value.name')->permission('ROLE_DENIED'),
            ],
            static fn (DataTable $table): DataTable => $table->data([
                ['name' => 'Ada', 'value' => (object) ['name' => 'secret', 'role' => 'admin']],
            ]),
        );

        $actual = $this->renderPayload($table);

        $this->assertSame(['name'], array_column($actual['columns'], 'name'));
        $this->assertSame(
            ['name' => 'Ada', 'value' => ['role' => 'admin']],
            $actual['data'][0],
        );
    }

    #[Test]
    public function it_filters_denied_static_actions_during_rendering(): void
    {
        $actions = (new Actions())->add(
            Action::detail()
                ->permission('ROLE_DENIED')
                ->linkToUrl(static fn (array $row): string => '/books/'.$row['id'])
        );

        $table = $this->inlineTable(
            [
                TextColumn::new('id'),
                ActionColumn::fromActions('actions', 'Actions', $actions),
            ],
            static fn (DataTable $table): DataTable => $table->data([
                ['id' => 5],
            ]),
        );

        $actual = $this->renderPayload($table);

        $actionColumn = array_values(array_filter(
            $actual['columns'],
            static fn (array $column): bool => 'actions' === $column['name'],
        ))[0];

        $this->assertSame([], $actionColumn['actions']);
        $this->assertArrayNotHasKey('__ux_datatables_actions', $actual['data'][0]);

        $configuredActionColumn = $this->container->get('test.datatables.runtime')->definition($table)->table->getColumnByName('actions');
        $this->assertInstanceOf(ActionColumn::class, $configuredActionColumn);
        $this->assertSame(1, $configuredActionColumn->getActions()?->count());
    }

    #[Test]
    public function it_drops_the_selection_it_enabled_for_bulk_actions_the_user_may_not_run(): void
    {
        $actual = $this->renderPayload($this->bulkTable('ROLE_DENIED'));

        $this->assertArrayNotHasKey('bulkActions', $actual);
        $this->assertArrayNotHasKey('select', $actual);
    }

    #[Test]
    public function it_keeps_a_selection_the_table_declared_when_every_bulk_action_is_denied(): void
    {
        $actual = $this->renderPayload($this->bulkTable(
            'ROLE_DENIED',
            static fn (DataTable $table): DataTable => $table->extensions([new SelectExtension(style: SelectStyle::MULTI)]),
        ));

        $this->assertArrayNotHasKey('bulkActions', $actual);
        $this->assertSame('multi', $actual['select']['style']);
    }

    #[Test]
    public function it_keeps_the_bulk_actions_selection_while_one_action_is_allowed(): void
    {
        $actual = $this->renderPayload($this->bulkTable(null));

        $this->assertSame(['approve'], array_column($actual['bulkActions']['actions'], 'name'));
        $this->assertTrue($actual['select']['withCheckbox']);
    }

    #[Test]
    public function it_keeps_unauthorized_columns_on_a_shared_abstract_datatable_after_render(): void
    {
        $table = new ConfigurableDataTable(
            [
                TextColumn::new('id'),
                TextColumn::new('secret')->permission('ROLE_DENIED'),
            ],
            configureTable: static fn (DataTable $table): DataTable => $table->data([
                ['id' => 5, 'secret' => 'hidden'],
            ]),
        );

        $this->assertSame(['id', 'secret'], array_map(
            static fn (mixed $column): string => $column->getName(),
            array_values($this->container->get('test.datatables.runtime')->definition($table)->table->getColumns()),
        ));

        $actual = $this->renderPayload($table);

        $this->assertSame(['id'], array_column($actual['columns'], 'name'));
        $this->assertSame(['id' => 5], $actual['data'][0]);
        $this->assertSame(['id', 'secret'], array_map(
            static fn (mixed $column): string => $column->getName(),
            array_values($this->container->get('test.datatables.runtime')->definition($table)->table->getColumns()),
        ));
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

        $this->assertSame($expectedProviderCalls, $table->providerCalls['count']);

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

        yield 'table with explicit data' => ['data', 0, [['id' => 99, 'title' => 'Manual']]];
    }

    #[Test]
    public function it_renders_template_columns_in_service_managed_server_side_ajax_response(): void
    {
        /** @var AbstractDataTable $table */
        $table    = $this->container->get('test.datatables.server_side_template');
        $response = $this->container->get('test.datatables.runtime')->respond($table, Request::create('/datatable/books', 'GET', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'search'  => ['value' => '', 'regex' => 'false'],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'status', 'name' => 'status_display', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ]));

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

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

    private function createInlineTable(string $mode): AbstractDataTable
    {
        if ('actions' === $mode) {
            $actions = (new Actions())->add(
                Action::detail()
                    ->className('btn btn-info')
                    ->linkToUrl(static fn (array $row): string => '/books/'.$row['id'])
            );

            return $this->inlineTable(
                [
                    TextColumn::new('id'),
                    ActionColumn::fromActions('actions', 'Actions', $actions),
                ],
                static fn (DataTable $table): DataTable => $table->data([['id' => 5]]),
            );
        }

        $columns = [
            TextColumn::new('id'),
            TemplateColumn::new('status_display')
                ->field('status')
                ->template('datatable/columns/status_badge.html.twig'),
        ];

        if ('ajax' === $mode) {
            return $this->inlineTable(
                $columns,
                static fn (DataTable $table): DataTable => $table->ajax('/api/items'),
            );
        }

        return $this->inlineTable(
            $columns,
            static fn (DataTable $table): DataTable => $table->data([['id' => 5, 'status' => 'active']]),
        );
    }

    private function createInlineObjectTable(string $mode): AbstractDataTable
    {
        $rows     = [new InlineBook(id: 5, title: 'Dune', status: 'active')];
        $template = 'configured' !== $mode
            ? 'datatable/columns/status_badge.html.twig'
            : 'datatable/columns/entity_status_badge.html.twig';

        $table = new ConfigurableDataTable(
            [
                TextColumn::new('id'),
                TextColumn::new('title'),
                TemplateColumn::new('status_display')
                    ->field('status')
                    ->template($template),
            ],
            actions: static fn (Actions $actions): Actions => $actions->add(
                Action::detail()->linkToUrl(static fn (InlineBook $book): string => '/books/'.$book->getId())
            ),
            configureTable: 'configured' === $mode
                ? static fn (DataTable $table): DataTable => $table->data($rows)
                : null,
        );

        return $table;
    }

    /**
     * @return array<string, mixed>
     */
    private function renderInlineObjectPayload(string $mode): array
    {
        $rows = 'argument' === $mode ? [new InlineBook(id: 5, title: 'Dune', status: 'active')] : null;

        return $this->payloadOf($this->container->get('test.datatables.twig_runtime')->renderDataTable($this->createInlineObjectTable($mode), rows: $rows));
    }

    /**
     * @param list<ColumnInterface>                 $columns
     * @param (\Closure(DataTable): DataTable)|null $configureTable
     */
    private function inlineTable(array $columns, ?\Closure $configureTable = null): ConfigurableDataTable
    {
        return new ConfigurableDataTable($columns, configureTable: $configureTable);
    }

    /**
     * @param (\Closure(DataTable): DataTable)|null $configureTable
     */
    private function bulkTable(?string $permission, ?\Closure $configureTable = null): ConfigurableDataTable
    {
        $action = BulkAction::new('approve');

        if (null !== $permission) {
            $action->permission($permission);
        }

        return new ConfigurableDataTable(
            [TextColumn::new('id')],
            configureTable: static fn (DataTable $table): DataTable => (null === $configureTable ? $table : $configureTable($table))->data([['id' => 1]]),
            bulkActions: static fn (BulkActions $actions): BulkActions => $actions->add($action),
        );
    }

    #[Test]
    public function it_renders_a_table_whose_only_edit_action_links_to_a_url_without_building_its_provider(): void
    {
        $table = new LinkEditDataTable();

        $this->renderPayload($table);

        $this->assertSame(0, $table->providerCalls['count']);
    }

    #[Test]
    #[DataProvider('dataSourceModes')]
    public function it_refuses_at_render_a_table_with_actions_on_a_provider_that_cannot_check_the_row_scope(string $mode): void
    {
        $table = new UnscopedActionsDataTable($mode);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('unscopedMutations()');

        $this->renderPayload($table);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dataSourceModes(): iterable
    {
        yield 'client side' => ['default'];
        yield 'server side' => ['serverSide'];
        yield 'ajax' => ['ajax'];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadOf(string $rendered): array
    {
        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);

        return $this->decodePayload($dom->getElementsByTagName('table')->item(0));
    }

    /**
     * @return array<string, mixed>
     */
    private function renderPayload(AbstractDataTable $table): array
    {
        return $this->decodePayload($this->renderTableElement($table));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function renderTableElement(AbstractDataTable $table, array $attributes = []): \DOMElement
    {
        $rendered = $this->container->get('test.datatables.twig_runtime')->renderDataTable($table, $attributes);

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

#[AsDataTable(entityClass: InlineBook::class)]
final class LinkEditDataTable extends AbstractDataTable
{
    /** @var \ArrayObject<string, int> shared with the bound copy the runtime calls */
    public \ArrayObject $providerCalls;

    public function __construct()
    {
        $this->providerCalls = new \ArrayObject(['count' => 0]);
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->ajax('/own-endpoint');
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::edit()->linkToUrl('/books/edit'));
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        ++$this->providerCalls['count'];

        return null;
    }
}

final class UnscopedActionsDataTable extends AbstractDataTable
{
    public function __construct(private readonly string $mode)
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return match ($this->mode) {
            'serverSide' => $table->serverSide(),
            'ajax'       => $table->ajax('/books.json'),
            default      => $table,
        };
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::delete());
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([], $this->createRowMapper());
    }
}

final class ProviderHydratedDataTable extends AbstractDataTable
{
    /** @var \ArrayObject<string, int> shared with the bound copy the runtime calls */
    public \ArrayObject $providerCalls;

    /**
     * @param list<InlineBook> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $mode = 'default',
    ) {
        $this->providerCalls = new \ArrayObject(['count' => 0]);
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
        ++$this->providerCalls['count'];

        return new ArrayDataProvider($this->items, $this->createRowMapper());
    }
}
