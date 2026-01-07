<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\Search;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SearchTest extends TestCase
{
    public function testFromRequest(): void
    {
        $request = new Request(
            query: [
                'search' => [
                    'value' => 'test',
                    'regex' => false,
                ],
            ]
        );

        $search = Search::fromRequest($request);

        $this->assertEquals('test', $search->value);
        $this->assertFalse($search->regex);
    }
}
