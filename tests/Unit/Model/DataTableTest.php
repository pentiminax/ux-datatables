<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ResponsiveExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\Options\SearchOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
