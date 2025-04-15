<?php

namespace Pentiminax\UX\DataTables\Tests\Model;

use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\DataTableOptions;
use PHPUnit\Framework\TestCase;

class DataTableOptionsTest extends TestCase
{
    public function testDataTableOptions(): void
    {
        $options = new DataTableOptions([
            'language' => 'en-GB',
            'search' => [
                'search' => 'Alice'
            ]
        ]);

        $this->assertEquals(Language::EN->getUrl(), $options['language']['url']);
        $this->assertEquals('Alice', $options['search']['search']);
    }
}