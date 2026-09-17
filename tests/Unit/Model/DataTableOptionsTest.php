<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\DataTableOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTableOptions::class)]
final class DataTableOptionsTest extends TestCase
{
    #[Test]
    public function it_processes_datatable_options(): void
    {
        $options = new DataTableOptions([
            'language' => 'en-GB',
            'search'   => [
                'search' => 'Alice',
            ],
        ]);

        $this->assertEquals(Language::EN->getUrl(), $options->get('language')['url']);
        $this->assertEquals('Alice', $options->get('search')['search']);
    }

    /**
     * @param array<string, mixed> $layout
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('provideLayouts')]
    public function it_normalizes_layout(array $layout, array $expected): void
    {
        $options = new DataTableOptions(['layout' => $layout]);

        $this->assertSame($expected, $options->getOptions()['layout']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function provideLayouts(): iterable
    {
        yield 'single features' => [
            [
                'topStart'    => Feature::PAGE_LENGTH,
                'topEnd'      => Feature::SEARCH,
                'bottomStart' => Feature::INFO,
                'bottomEnd'   => Feature::PAGING,
                'top2Start'   => Feature::SEARCH_BUILDER,
                'top2End'     => Feature::SEARCH_PANES,
            ],
            [
                'topStart'    => 'pageLength',
                'topEnd'      => 'search',
                'bottomStart' => 'info',
                'bottomEnd'   => 'paging',
                'top2Start'   => 'searchBuilder',
                'top2End'     => 'searchPanes',
            ],
        ];

        yield 'array of features' => [
            ['topEnd' => [Feature::SEARCH, Feature::BUTTONS]],
            ['topEnd' => ['search', 'buttons']],
        ];

        yield 'null values are preserved' => [
            ['topStart' => Feature::PAGE_LENGTH, 'bottomStart' => null],
            ['topStart' => 'pageLength', 'bottomStart' => null],
        ];

        yield 'string values are preserved' => [
            ['top' => '<h2>Title</h2>', 'topStart' => 'customPlugin'],
            ['top' => '<h2>Title</h2>', 'topStart' => 'customPlugin'],
        ];

        yield 'plain string array' => [
            ['topStart' => 'pageLength', 'topEnd' => 'search'],
            ['topStart' => 'pageLength', 'topEnd' => 'search'],
        ];
    }

    #[Test]
    public function it_exposes_typed_accessors_for_arbitrary_options(): void
    {
        $options = new DataTableOptions();

        $this->assertFalse($options->has('foo'));
        $this->assertNull($options->get('foo'));

        $options->set('foo', 'bar');

        $this->assertTrue($options->has('foo'));
        $this->assertSame('bar', $options->get('foo'));
        $this->assertSame('bar', $options->getOptions()['foo']);

        $options->remove('foo');

        $this->assertFalse($options->has('foo'));
        $this->assertNull($options->get('foo'));
    }
}
