<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\DataTableOptions;
use PHPUnit\Framework\Attributes\CoversClass;
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

    #[Test]
    public function it_normalizes_layout_with_single_features(): void
    {
        $options = new DataTableOptions([
            'layout' => [
                'topStart'    => Feature::PAGE_LENGTH,
                'topEnd'      => Feature::SEARCH,
                'bottomStart' => Feature::INFO,
                'bottomEnd'   => Feature::PAGING,
                'top2Start'   => Feature::SEARCH_BUILDER,
                'top2End'     => Feature::SEARCH_PANES,
            ],
        ]);

        $this->assertSame([
            'topStart'    => 'pageLength',
            'topEnd'      => 'search',
            'bottomStart' => 'info',
            'bottomEnd'   => 'paging',
            'top2Start'   => 'searchBuilder',
            'top2End'     => 'searchPanes',
        ], $options->getOptions()['layout']);
    }

    #[Test]
    public function it_normalizes_layout_with_array_of_features(): void
    {
        $options = new DataTableOptions([
            'layout' => [
                'topEnd' => [Feature::SEARCH, Feature::BUTTONS],
            ],
        ]);

        $this->assertSame([
            'topEnd' => ['search', 'buttons'],
        ], $options->getOptions()['layout']);
    }

    #[Test]
    public function it_preserves_null_values_in_layout(): void
    {
        $options = new DataTableOptions([
            'layout' => [
                'topStart'    => Feature::PAGE_LENGTH,
                'bottomStart' => null,
            ],
        ]);

        $this->assertSame([
            'topStart'    => 'pageLength',
            'bottomStart' => null,
        ], $options->getOptions()['layout']);
    }

    #[Test]
    public function it_preserves_string_values_in_layout(): void
    {
        $options = new DataTableOptions([
            'layout' => [
                'top'      => '<h2>Title</h2>',
                'topStart' => 'customPlugin',
            ],
        ]);

        $this->assertSame([
            'top'      => '<h2>Title</h2>',
            'topStart' => 'customPlugin',
        ], $options->getOptions()['layout']);
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

    #[Test]
    public function it_no_longer_implements_array_access(): void
    {
        $this->assertFalse(is_subclass_of(DataTableOptions::class, \ArrayAccess::class));
    }

    #[Test]
    public function it_handles_layout_as_plain_string_array(): void
    {
        $options = new DataTableOptions([
            'layout' => [
                'topStart' => 'pageLength',
                'topEnd'   => 'search',
            ],
        ]);

        $this->assertSame([
            'topStart' => 'pageLength',
            'topEnd'   => 'search',
        ], $options->getOptions()['layout']);
    }
}
