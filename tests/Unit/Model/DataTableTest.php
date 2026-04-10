<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\Options\SearchOption;
use PHPUnit\Framework\Attributes\CoversClass;
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
            'responsive'    => true,
        ];

        $this->assertEquals($expectedExtensions, $table->getExtensions());
        $this->assertTrue($table->getOption('apiPlatform'));
    }

    #[Test]
    public function it_configures_layout_with_array(): void
    {
        $table = new DataTable('testTable');

        $table->layout([
            'topStart'    => Feature::BUTTONS,
            'topEnd'      => Feature::PAGE_LENGTH,
            'bottomStart' => Feature::PAGING,
            'bottomEnd'   => Feature::INFO,
        ]);

        $this->assertSame([
            'topStart'    => 'buttons',
            'topEnd'      => 'pageLength',
            'bottomStart' => 'paging',
            'bottomEnd'   => 'info',
        ], $table->getOptions()['layout']);
    }

    #[Test]
    public function it_configures_layout_with_multi_features(): void
    {
        $table = new DataTable('testTable');

        $table->layout([
            'topEnd' => [Feature::SEARCH, Feature::BUTTONS],
        ]);

        $this->assertSame([
            'topEnd' => ['search', 'buttons'],
        ], $table->getOptions()['layout']);
    }

    #[Test]
    public function it_configures_layout_with_null_and_custom_strings(): void
    {
        $table = new DataTable('testTable');

        $table->layout([
            'top'         => '<h2>Title</h2>',
            'topStart'    => Feature::PAGE_LENGTH,
            'bottomStart' => null,
            'bottomEnd'   => Feature::PAGING,
        ]);

        $layout = $table->getOptions()['layout'];

        $this->assertSame('<h2>Title</h2>', $layout['top']);
        $this->assertSame('pageLength', $layout['topStart']);
        $this->assertNull($layout['bottomStart']);
        $this->assertSame('paging', $layout['bottomEnd']);
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
    public function it_configures_mercure_with_default_topic(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure(hubUrl: '/.well-known/mercure');

        $config = $table->getMercureConfig();

        $this->assertInstanceOf(MercureConfig::class, $config);
        $this->assertSame('/.well-known/mercure', $config->hubUrl);
        $this->assertSame(['/datatables/product-data-tables/{id}'], $config->topics);
        $this->assertFalse($config->withCredentials);
        $this->assertNull($config->debounceMs);
    }

    #[Test]
    public function it_configures_mercure_with_custom_topic(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure(hubUrl: '/.well-known/mercure', topics: ['my/custom/topic']);

        $config = $table->getMercureConfig();

        $this->assertSame(['my/custom/topic'], $config?->topics);
    }

    #[Test]
    public function it_configures_mercure_with_multiple_topics(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure(hubUrl: '/.well-known/mercure', topics: ['/api/products/{id}', '/api/categories/{id}']);

        $config = $table->getMercureConfig();

        $this->assertSame(['/api/products/{id}', '/api/categories/{id}'], $config?->topics);
    }

    #[Test]
    public function it_includes_mercure_in_get_options(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure(hubUrl: '/.well-known/mercure', debounceMs: 300);

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
    public function mercure_method_is_fluent(): void
    {
        $table = new DataTable('test');

        $this->assertSame($table, $table->mercure('/.well-known/mercure'));
    }

    #[Test]
    public function it_returns_column_objects_as_the_single_source_of_truth(): void
    {
        $firstColumn  = TextColumn::new('first_name', 'First name');
        $secondColumn = TextColumn::new('last_name', 'Last name');

        $table = (new DataTable('users'))->columns([$firstColumn, $secondColumn]);

        $this->assertSame([
            'first_name' => $firstColumn,
            'last_name'  => $secondColumn,
        ], $table->getColumns());
    }

    #[Test]
    public function it_builds_column_definitions_from_column_objects(): void
    {
        $table = (new DataTable('users'))->columns([
            TextColumn::new('first_name', 'First name'),
        ]);

        $this->assertSame([
            [
                'data'       => 'first_name',
                'name'       => 'first_name',
                'orderable'  => true,
                'searchable' => true,
                'title'      => 'First name',
                'type'       => 'string',
                'visible'    => true,
                'field'      => 'first_name',
            ],
        ], $table->getColumnDefinitions());
        $this->assertSame($table->getColumnDefinitions(), $table->getOptions()['columns']);
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
}
