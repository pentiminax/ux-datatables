<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Enum\StyleFramework;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ResponsiveExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\Options\SearchOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * @internal
 */
#[CoversClass(DataTable::class)]
final class DataTableTest extends TestCase
{
    #[Test]
    public function it_configures_datatable_options(): void
    {
        $selectExtension = new SelectExtension();

        $table = (new DataTable('tableId'))
                ->autoWidth(true)
                ->ajax(url: '/url')
                ->caption('Table caption')
                ->deferRender(true)
                ->displayStart(10)
                ->info(true)
                ->lengthChange(true)
                ->ordering()
                ->withoutPaging()
                ->processing()
                ->scrollX(true)
                ->scrollY('200px')
                ->search('search')
                ->searching()
                ->serverSide()
                ->apiPlatform()
                ->stateSave()
                ->pageLength(10)
                ->language(Language::FR)
                ->lengthMenu([10, 25, 50])
                ->responsive()
                ->columnControl()
                ->withSearchOption(SearchOption::new())
                ->extensions([$selectExtension]);

        $this->assertEquals('tableId', $table->getId());

        $expectedExtensions = [
            'columnControl' => (new ColumnControlExtension())->jsonSerialize(),
            'select'        => $selectExtension->jsonSerialize(),
            'responsive'    => (new ResponsiveExtension())->jsonSerialize(),
        ];

        $this->assertEquals($expectedExtensions, $table->getExtensions());
        $this->assertTrue($table->getOption('apiPlatform'));
    }

    #[Test]
    public function it_configures_edit_modal_overrides(): void
    {
        $table = (new DataTable('tableId'))
            ->editModalTemplate('custom/modal.html.twig')
            ->editModalAdapter('tw');

        $this->assertSame('custom/modal.html.twig', $table->getEditModalTemplate());
        $this->assertSame('tw', $table->getEditModalAdapter());
    }

    #[Test]
    public function it_stores_expression_permissions(): void
    {
        $expression = new Expression('"ROLE_ADMIN" in role_names');
        $table      = (new DataTable('tableId'))->setPermission($expression);

        $this->assertSame($expression, $table->getPermission());
    }

    #[Test]
    public function it_normalizes_the_configured_layout(): void
    {
        $table = (new DataTable('testTable'))->layout([
            'top'         => '<h2>Title</h2>',
            'topStart'    => Feature::BUTTONS,
            'topEnd'      => [Feature::SEARCH, Feature::PAGE_LENGTH],
            'bottomStart' => null,
            'bottomEnd'   => Feature::PAGING,
        ]);

        $this->assertSame([
            'top'         => '<h2>Title</h2>',
            'topStart'    => 'buttons',
            'topEnd'      => ['search', 'pageLength'],
            'bottomStart' => null,
            'bottomEnd'   => 'paging',
        ], $table->getOptions()['layout']);
    }

    #[Test]
    public function buttons_registers_the_extension_and_places_the_container_in_the_layout(): void
    {
        $table = (new DataTable('testTable'))->buttons([ButtonType::CSV, ButtonType::EXCEL]);

        $expectedButtons = (new ButtonsExtension([ButtonType::CSV, ButtonType::EXCEL]))->jsonSerialize();

        $this->assertSame(
            ['buttons' => $expectedButtons],
            $table->getOptions()['layout']['topStart'],
        );
    }

    #[Test]
    public function buttons_places_the_container_at_the_requested_position(): void
    {
        $table = (new DataTable('testTable'))->buttons([ButtonType::CSV], 'bottomEnd');

        $layout = $table->getOptions()['layout'];

        $this->assertArrayNotHasKey('topStart', $layout);
        $this->assertSame(
            ['buttons' => (new ButtonsExtension([ButtonType::CSV]))->jsonSerialize()],
            $layout['bottomEnd'],
        );
    }

    #[Test]
    public function buttons_keeps_the_feature_already_declared_in_the_target_position(): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['topStart' => Feature::PAGE_LENGTH])
            ->buttons([ButtonType::CSV]);

        $this->assertSame(
            ['pageLength', ['buttons' => (new ButtonsExtension([ButtonType::CSV]))->jsonSerialize()]],
            $table->getOptions()['layout']['topStart'],
        );
    }

    #[Test]
    public function buttons_appends_the_container_to_a_list_of_features(): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['topEnd' => [Feature::SEARCH, Feature::PAGE_LENGTH]])
            ->buttons([ButtonType::CSV], 'topEnd');

        $this->assertSame(
            ['search', 'pageLength', ['buttons' => (new ButtonsExtension([ButtonType::CSV]))->jsonSerialize()]],
            $table->getOptions()['layout']['topEnd'],
        );
    }

    #[Test]
    public function buttons_wraps_a_raw_feature_object_instead_of_merging_into_it(): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['top' => ['div' => ['html' => '<h2>Title</h2>']]])
            ->buttons([ButtonType::CSV], 'top');

        $this->assertSame(
            [
                ['div' => ['html' => '<h2>Title</h2>']],
                ['buttons' => (new ButtonsExtension([ButtonType::CSV]))->jsonSerialize()],
            ],
            $table->getOptions()['layout']['top'],
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function alreadyDeclaredButtonsProvider(): iterable
    {
        yield 'feature enum' => [Feature::BUTTONS];
        yield 'raw feature name' => ['buttons'];
        yield 'inside a list' => [[Feature::SEARCH, Feature::BUTTONS]];
        yield 'raw buttons feature object' => [['buttons' => [['extend' => 'csv']]]];
        yield 'raw buttons object in a list' => [[Feature::SEARCH, ['buttons' => [['extend' => 'csv']]]]];
    }

    #[Test]
    #[DataProvider('alreadyDeclaredButtonsProvider')]
    public function buttons_does_not_duplicate_a_container_the_layout_already_declares(mixed $slot): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['topStart' => $slot])
            ->buttons([ButtonType::CSV]);

        $topStart = $table->getOptions()['layout']['topStart'];
        $entries  = \is_array($topStart) && array_is_list($topStart) ? $topStart : [$topStart];

        $containers = \count(array_filter(
            $entries,
            static fn ($entry): bool => \is_array($entry) && \array_key_exists('buttons', $entry),
        ));

        $this->assertSame(1, $containers);
    }

    #[Test]
    public function buttons_leaves_a_raw_buttons_feature_object_alone(): void
    {
        $rawButtons = ['buttons' => [['extend' => 'csv', 'text' => 'Hand-rolled']]];

        $table = (new DataTable('testTable'))
            ->layout(['topStart' => $rawButtons])
            ->buttons([ButtonType::EXCEL]);

        $this->assertSame($rawButtons, $table->getOptions()['layout']['topStart']);
    }

    #[Test]
    public function buttons_accepts_customized_button_objects(): void
    {
        $table = (new DataTable('testTable'))->buttons([
            Button::csv()->text('Export CSV'),
            Button::colVis()->text('Columns'),
        ]);

        $expectedButtons = (new ButtonsExtension([
            Button::csv()->text('Export CSV'),
            Button::colVis()->text('Columns'),
        ]))->jsonSerialize();

        $this->assertSame(
            ['buttons' => $expectedButtons],
            $table->getOptions()['layout']['topStart'],
        );
    }

    #[Test]
    public function buttons_replaces_a_previously_registered_button_list(): void
    {
        $table = (new DataTable('testTable'))
            ->buttons([ButtonType::CSV])
            ->buttons([ButtonType::PRINT]);

        $this->assertSame(
            ['buttons' => (new ButtonsExtension([ButtonType::PRINT]))->jsonSerialize()],
            $table->getOptions()['layout']['topStart'],
        );
    }

    #[Test]
    public function it_configures_paging_option(): void
    {
        $table = new DataTable('testTable');

        $table->paging(
            boundaryNumbers: false,
            buttons: 5,
            firstLast: false,
            numbers: false,
            previousNext: false
        );

        $expectedPaging = [
            'boundaryNumbers' => false,
            'buttons'         => 5,
            'firstLast'       => false,
            'numbers'         => false,
            'previousNext'    => false,
        ];

        $this->assertSame($expectedPaging, $table->getOption('paging'));
    }

    #[Test]
    public function it_rewrites_paging_markers_into_the_layout_feature(): void
    {
        $table = (new DataTable('testTable'))
            ->layout([
                'topStart'    => Feature::PAGE_LENGTH,
                'topEnd'      => Feature::SEARCH,
                'bottomStart' => Feature::INFO,
                'bottomEnd'   => Feature::PAGING,
            ])
            ->paging(buttons: 5, firstLast: false);

        $expectedPaging = [
            'boundaryNumbers' => true,
            'buttons'         => 5,
            'firstLast'       => false,
            'numbers'         => true,
            'previousNext'    => true,
        ];

        $this->assertSame($expectedPaging, $table->getOption('paging'));
        $this->assertTrue($table->getOptions()['paging']);
        $this->assertSame(['paging' => $expectedPaging], $table->getOptions()['layout']['bottomEnd']);
    }

    #[Test]
    public function without_paging_leaves_the_layout_marker_and_disables_pagination(): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['bottomEnd' => Feature::PAGING])
            ->withoutPaging();

        $this->assertFalse($table->getOptions()['paging']);
        $this->assertSame('paging', $table->getOptions()['layout']['bottomEnd']);
    }

    #[Test]
    public function an_explicit_layout_paging_object_wins_over_paging_options(): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['bottomEnd' => ['paging' => ['buttons' => 3]]])
            ->paging(buttons: 5);

        $this->assertTrue($table->getOptions()['paging']);
        $this->assertSame(['paging' => ['buttons' => 3]], $table->getOptions()['layout']['bottomEnd']);
    }

    #[Test]
    public function paging_options_do_not_invent_a_layout_slot(): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['bottomEnd' => Feature::INFO])
            ->paging(buttons: 5);

        $this->assertTrue($table->getOptions()['paging']);
        $this->assertSame('info', $table->getOptions()['layout']['bottomEnd']);
        $this->assertArrayNotHasKey('bottomStart', $table->getOptions()['layout']);
    }

    #[Test]
    public function paging_options_replace_a_marker_inside_a_layout_list(): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['bottomEnd' => [Feature::INFO, Feature::PAGING]])
            ->paging(buttons: 5, firstLast: false);

        $expectedPaging = [
            'boundaryNumbers' => true,
            'buttons'         => 5,
            'firstLast'       => false,
            'numbers'         => true,
            'previousNext'    => true,
        ];

        $this->assertSame(
            ['info', ['paging' => $expectedPaging]],
            $table->getOptions()['layout']['bottomEnd']
        );
    }

    #[Test]
    public function paging_options_fill_a_boolean_paging_feature_object(): void
    {
        $table = (new DataTable('testTable'))
            ->layout(['bottomEnd' => ['paging' => true]])
            ->paging(buttons: 5);

        $this->assertSame(5, $table->getOptions()['layout']['bottomEnd']['paging']['buttons']);
    }

    /**
     * @param string[] $topics
     * @param string[] $expectedTopics
     */
    #[Test]
    #[DataProvider('provideMercureTopics')]
    public function it_configures_mercure_topics(array $topics, array $expectedTopics): void
    {
        $config = (new DataTable('ProductDataTable'))->mercure(topics: $topics)->getMercureConfig();

        $this->assertSame($expectedTopics, $config?->topics);
        $this->assertNull($config?->hubUrl);
        $this->assertFalse($config?->withCredentials);
        $this->assertNull($config?->debounceMs);
    }

    /**
     * @return iterable<string, array{string[], string[]}>
     */
    public static function provideMercureTopics(): iterable
    {
        yield 'default topic' => [[], ['/datatables/product-data-tables/{id}']];
        yield 'custom topic' => [['my/custom/topic'], ['my/custom/topic']];
        yield 'multiple topics' => [
            ['/api/products/{id}', '/api/categories/{id}'],
            ['/api/products/{id}', '/api/categories/{id}'],
        ];
    }

    #[Test]
    public function it_includes_mercure_in_get_options(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure(debounceMs: 300);

        $table->setMercureConfig($table->getMercureConfig()->withHubUrl('/.well-known/mercure'));

        $options = $table->getOptions();

        $this->assertArrayHasKey('mercure', $options);
        $this->assertSame([
            'hubUrl'     => '/.well-known/mercure',
            'topics'     => ['/datatables/product-data-tables/{id}'],
            'debounceMs' => 300,
        ], $options['mercure']);
    }

    #[Test]
    public function it_does_not_include_mercure_in_get_options_when_not_configured(): void
    {
        $table   = new DataTable('ProductDataTable');
        $options = $table->getOptions();

        $this->assertArrayNotHasKey('mercure', $options);
    }

    #[Test]
    public function it_exposes_configured_columns_as_objects_and_definitions(): void
    {
        $firstColumn  = TextColumn::new('first_name', 'First name');
        $secondColumn = TextColumn::new('last_name', 'Last name');

        $table = (new DataTable('users'))->columns([$firstColumn, $secondColumn]);

        $this->assertSame([
            'first_name' => $firstColumn,
            'last_name'  => $secondColumn,
        ], $table->getColumns());

        $definitions = $table->getColumnDefinitions();

        $this->assertCount(2, $definitions);
        $this->assertSame([
            'className'  => 'dt-exportable',
            'data'       => 'first_name',
            'name'       => 'first_name',
            'orderable'  => true,
            'searchable' => true,
            'title'      => 'First name',
            'type'       => 'string',
            'visible'    => true,
            'field'      => 'first_name',
        ], $definitions[0]);
        $this->assertSame($definitions, $table->getOptions()['columns']);
    }

    #[Test]
    public function it_keeps_serialized_columns_in_sync_when_a_column_is_mutated_after_configuration(): void
    {
        $column = TextColumn::new('status', 'Status');
        $table  = (new DataTable('users'))->columns([$column]);

        $column->setTitle('Translated status');

        $this->assertSame('Translated status', $table->getOptions()['columns'][0]['title']);
        $this->assertSame('Translated status', $table->getColumnDefinitions()[0]['title']);
    }

    #[Test]
    public function it_adds_single_columns_to_both_object_and_serialized_views(): void
    {
        $column = TextColumn::new('email', 'Email');
        $table  = (new DataTable('users'))->add($column);

        $this->assertSame(['email' => $column], $table->getColumns());
        $this->assertSame('Email', $table->getColumnDefinitions()[0]['title']);
    }

    #[Test]
    public function it_rejects_column_control_content_without_a_target(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column control content needs a target row. Pass a header row index or a "tfoot" string, for example columnControl(target: 1, content: [\'searchText\']).');

        (new DataTable('books'))->columnControl(content: ['searchText']);
    }

    #[Test]
    public function it_keeps_the_column_control_defaults_when_no_target_is_given(): void
    {
        $table = (new DataTable('books'))->columnControl();

        $this->assertSame(
            (new ColumnControlExtension())->jsonSerialize(),
            $table->getExtensionsCollection()->jsonSerialize()['columnControl']
        );
    }

    #[Test]
    public function it_targets_the_footer_with_a_search_control(): void
    {
        $table = (new DataTable('books'))->columnControl(target: 'tfoot');

        $this->assertSame(
            [['target' => 'tfoot', 'content' => ['search']]],
            $table->getExtensionsCollection()->jsonSerialize()['columnControl']
        );
    }

    #[Test]
    public function it_targets_a_header_row_with_explicit_control_content(): void
    {
        $table = (new DataTable('books'))->columnControl(target: 1, content: ['searchText']);

        $this->assertSame(
            [['target' => 1, 'content' => ['searchText']]],
            $table->getExtensionsCollection()->jsonSerialize()['columnControl']
        );
    }

    /**
     * @param array<string, bool>  $keys
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('provideUrlStates')]
    public function it_configures_url_state(array $keys, string $prefix, array $expected): void
    {
        $table = (new DataTable('users'))->urlState($keys, $prefix);

        $this->assertSame($expected, $table->getOption('urlState'));
    }

    /**
     * @return iterable<string, array{array<string, bool>, string, array<string, mixed>}>
     */
    public static function provideUrlStates(): iterable
    {
        yield 'all keys enabled by default' => [[], '', [
            'search'     => true,
            'order'      => true,
            'page'       => true,
            'pageLength' => true,
            'prefix'     => '',
        ]];

        yield 'partial keys' => [['page' => false], '', [
            'search'     => true,
            'order'      => true,
            'page'       => false,
            'pageLength' => true,
            'prefix'     => '',
        ]];

        yield 'prefix only' => [[], 'usersTable', [
            'search'     => true,
            'order'      => true,
            'page'       => true,
            'pageLength' => true,
            'prefix'     => 'usersTable',
        ]];

        yield 'granular keys and prefix' => [['search' => true, 'order' => false], 'u', [
            'search'     => true,
            'order'      => false,
            'page'       => true,
            'pageLength' => true,
            'prefix'     => 'u',
        ]];
    }

    #[Test]
    public function it_stores_forwarded_query_parameters(): void
    {
        $table = new DataTable('users');

        $this->assertSame([], $table->getForwardedQueryParameters());

        $table->forwardQueryParameters(['q', 'pending']);

        $this->assertSame(['q', 'pending'], $table->getForwardedQueryParameters());
    }

    #[Test]
    public function it_merges_ajax_data_into_existing_ajax_payload(): void
    {
        $table = (new DataTable('users'))
            ->ajaxRequestData('/endpoint', ['table' => 'token'])
            ->mergeAjaxData(['q' => 'foo']);

        $this->assertSame(['table' => 'token', 'q' => 'foo'], $table->getOption('ajax')['data']);
    }

    #[Test]
    public function it_creates_ajax_data_key_when_merging_into_ajax_without_data(): void
    {
        $table = (new DataTable('users'))
            ->ajax('/endpoint')
            ->mergeAjaxData(['q' => 'foo']);

        $this->assertSame(['q' => 'foo'], $table->getOption('ajax')['data']);
    }

    #[Test]
    public function it_does_not_merge_ajax_data_when_no_ajax_source_is_configured(): void
    {
        $table = (new DataTable('users'))->mergeAjaxData(['q' => 'foo']);

        $this->assertNull($table->getOption('ajax'));
    }

    #[Test]
    public function it_serializes_an_explicit_style_framework_as_its_frontend_key(): void
    {
        $table = (new DataTable('users'))->styleFramework(StyleFramework::Bootstrap5);

        $this->assertSame('bs5', $table->getOption('styleFramework'));
        $this->assertSame('bs5', $table->getOptions()['styleFramework']);
    }

    #[Test]
    public function it_has_no_style_framework_option_when_not_set(): void
    {
        $table = new DataTable('users');

        $this->assertNull($table->getOption('styleFramework'));
        $this->assertArrayNotHasKey('styleFramework', $table->getOptions());
    }
}
