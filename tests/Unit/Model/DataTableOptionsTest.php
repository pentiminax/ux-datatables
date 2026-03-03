<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableOptions;
use Pentiminax\UX\DataTables\Model\Options\LayoutOption;
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

        $this->assertEquals(Language::EN->getUrl(), $options['language']['url']);
        $this->assertEquals('Alice', $options['search']['search']);
    }

    #[Test]
    public function it_gets_options(): void
    {
        $options = new DataTableOptions([
            'layout' => new LayoutOption(new DataTable('testTable')),
        ]);

        $this->assertLayoutOption($options->getOptions());
    }

    private function assertLayoutOption(array $options): void
    {
        $this->assertArrayHasKey('layout', $options);
        $this->assertArrayHasKey('topStart', $options['layout']);
        $this->assertArrayHasKey('topEnd', $options['layout']);
        $this->assertArrayHasKey('bottomStart', $options['layout']);
        $this->assertArrayHasKey('bottomEnd', $options['layout']);
    }
}
