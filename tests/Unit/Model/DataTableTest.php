<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

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
    public function it_configures_layout_option(): void
    {
        $table = new DataTable('testTable');

        $table->layout(
            topStart: Feature::BUTTONS,
            topEnd: Feature::PAGE_LENGTH,
            bottomStart: Feature::PAGING,
            bottomEnd: Feature::INFO
        );

        $expectedLayout = [
            'topStart'    => 'buttons',
            'topEnd'      => 'pageLength',
            'bottomStart' => [
                'paging' => true,
            ],
            'bottomEnd' => 'info',
        ];

        $this->assertSame($expectedLayout, $table->getOption('layout')->jsonSerialize());
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
}
