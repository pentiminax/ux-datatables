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
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTablesExtension::class)]
final class DataTablesExtensionTest extends TestCase
{
    #[Test]
    public function it_renders_datatable(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableBuilderInterface $builder */
        $builder = $container->get('test.datatables.builder');

        $table = $builder
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

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable(
            $table,
            ['data-controller' => 'mycontroller', 'class' => 'myclass']
        );

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $this->assertSame('table', $tableEl->getAttribute('id'));
        $this->assertSame('mycontroller pentiminax--ux-datatables--datatable', $tableEl->getAttribute('data-controller'));
        $this->assertSame('myclass', $tableEl->getAttribute('class'));

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));
        $actual   = json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);

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
            'editModal'      => [
                'adapter' => null,
            ],
        ];

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_exposes_edit_modal_overrides_and_the_datatable_class(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $table = new EditModalConfiguredDataTable();

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));
        $actual   = json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('tw', $actual['editModal']['adapter']);
        $this->assertSame($table::class, $actual['dataTableClass']);
    }

    #[Test]
    public function it_uses_get_data_table_for_abstract_datatable(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

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

        $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $this->assertTrue($table->getDataTableCalled);
        $this->assertTrue($table->prepareForRenderingCalled);
    }

    #[Test]
    public function it_pre_renders_template_columns_in_inline_data(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableBuilderInterface $builder */
        $builder = $container->get('test.datatables.builder');

        $table = $builder->createDataTable('template_table');
        $table->columns([
            TextColumn::new('id'),
            TemplateColumn::new('status_display')
                ->setField('status')
                ->setTemplate('datatable/columns/status_badge.html.twig'),
        ]);
        $table->data([
            ['id' => 5, 'status' => 'active'],
        ]);

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));
        $actual   = json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('<span class="badge">5-active</span>', trim($actual['data'][0]['status']));
    }

    #[Test]
    public function it_skips_template_rendering_when_already_marked(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableBuilderInterface $builder */
        $builder = $container->get('test.datatables.builder');

        $table = $builder->createDataTable('template_table');
        $table->columns([
            TextColumn::new('id'),
            TemplateColumn::new('status_display')
                ->setField('status')
                ->setTemplate('datatable/columns/status_badge.html.twig'),
        ]);
        $table->data([
            ['id' => 5, 'status' => 'active'],
        ]);

        $table->markTemplateColumnsRendered();

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));
        $actual   = json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('active', $actual['data'][0]['status']);
    }

    #[Test]
    public function it_skips_template_rendering_when_no_inline_data(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableBuilderInterface $builder */
        $builder = $container->get('test.datatables.builder');

        $table = $builder->createDataTable('ajax_table');
        $table->columns([
            TextColumn::new('id'),
            TemplateColumn::new('status_display')
                ->setField('status')
                ->setTemplate('datatable/columns/status_badge.html.twig'),
        ]);
        $table->ajax('/api/items');

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));
        $actual   = json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('data', $actual);
        $this->assertFalse($table->areTemplateColumnsRendered());
    }

    #[Test]
    public function it_resolves_detail_action_urls_for_inline_data(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableBuilderInterface $builder */
        $builder = $container->get('test.datatables.builder');

        $actions = (new Actions())->add(
            Action::detail()
                ->setClassName('btn btn-info')
                ->linkToUrl(static fn (array $row): string => '/books/'.$row['id'])
        );

        $table = $builder->createDataTable('actions_table');
        $table->columns([
            TextColumn::new('id'),
            ActionColumn::fromActions('actions', 'Actions', $actions),
        ]);
        $table->data([
            ['id' => 5],
        ]);

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));
        $actual   = json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('/books/5', $actual['data'][0]['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_keeps_set_data_rows_prepared_during_rendering(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTablesExtension $twigExtension */
        $twigExtension = $container->get('test.datatables.twig_extension');
        $reflection    = new \ReflectionClass($twigExtension);

        $templateRendererProperty = $reflection->getProperty('templateColumnRenderer');
        $templateRendererProperty->setAccessible(true);

        $actionResolverProperty = $reflection->getProperty('actionRowDataResolver');
        $actionResolverProperty->setAccessible(true);

        $table = new InlinePreparedDataTable(
            $templateRendererProperty->getValue($twigExtension),
            $actionResolverProperty->getValue($twigExtension),
        );

        $table->setData([
            new InlineBookEntity(id: 5, status: 'active'),
        ]);

        $this->assertTrue($table->getDataTable()->areTemplateColumnsRendered());

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));
        $actual   = json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('<span class="badge">5-active</span>', trim($actual['data'][0]['status']));
        $this->assertSame('/books/5', $actual['data'][0]['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_auto_hydrates_client_side_abstract_datatable_from_provider(): void
    {
        $table = new ProviderHydratedDataTable([
            new ProviderHydratedBookEntity(id: 5, title: 'Dune'),
            new ProviderHydratedBookEntity(id: 9, title: 'Foundation'),
        ]);

        $actual = $this->renderPayload($table);

        $this->assertSame(1, $table->providerCalls);
        $this->assertSame('Dune', $actual['data'][0]['title']);
        $this->assertSame('Foundation', $actual['data'][1]['title']);
        $this->assertSame('/books/5', $actual['data'][0]['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_does_not_auto_hydrate_when_an_explicit_data_source_or_server_side_mode_is_configured(): void
    {
        foreach (['serverSide', 'ajax', 'data', 'apiPlatform'] as $mode) {
            $table = new ProviderHydratedDataTable([
                new ProviderHydratedBookEntity(id: 5, title: 'Dune'),
            ], $mode);

            $actual = $this->renderPayload($table);

            $this->assertSame(0, $table->providerCalls, $mode);

            if ('data' === $mode) {
                $this->assertSame([['id' => 99, 'title' => 'Manual']], $actual['data']);
            } else {
                $this->assertArrayNotHasKey('data', $actual, $mode);
            }
        }
    }

    private function renderPayload(AbstractDataTable|DataTable $table): array
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $rendered = $container->get('test.datatables.twig_extension')->renderDataTable($table);

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $tableEl = $dom->getElementsByTagName('table')->item(0);

        $jsonAttr = html_entity_decode($tableEl->getAttribute('data-pentiminax--ux-datatables--datatable-view-value'));

        return json_decode($jsonAttr, true, 512, JSON_THROW_ON_ERROR);
    }
}

final class EditModalConfiguredDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->editModalTemplate('custom/modal.html.twig')
            ->editModalAdapter('tw');
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('firstColumn');
    }
}

final readonly class InlineBookEntity
{
    public function __construct(
        private int $id,
        private string $status,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}

final class InlinePreparedDataTable extends AbstractDataTable
{
    public function __construct(
        \Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer $templateColumnRenderer,
        \Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver $actionRowDataResolver,
    ) {
        parent::__construct(
            runtimeFactory: new \Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory(
                templateColumnRenderer: $templateColumnRenderer,
                actionRowDataResolver: $actionRowDataResolver,
            ),
        );
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TemplateColumn::new('status_display')
            ->setField('status')
            ->setTemplate('datatable/columns/status_badge.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail()->linkToUrl(static fn (InlineBookEntity $book): string => '/books/'.$book->getId())
        );
    }
}

final readonly class ProviderHydratedBookEntity
{
    public function __construct(
        private int $id,
        private string $title,
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
}

final class ProviderHydratedDataTable extends AbstractDataTable
{
    public int $providerCalls = 0;

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
            Action::detail()->linkToUrl(static fn (ProviderHydratedBookEntity $book): string => '/books/'.$book->getId())
        );
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        ++$this->providerCalls;

        return new ArrayDataProvider($this->items, $this->createRowMapper());
    }
}
