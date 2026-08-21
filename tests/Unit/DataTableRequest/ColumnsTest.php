<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(Columns::class)]
final class ColumnsTest extends TestCase
{
    #[Test]
    public function it_parses_from_a_get_request_query_string(): void
    {
        $request = new Request(query: [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true],
            ],
        ]);

        $columns = Columns::fromRequest($request);

        $this->assertNotNull($columns->getColumnByName('name'));
    }

    /**
     * DataTable::ajax()'s $type parameter lets an app configure a POST request; DataTables
     * then puts the same parameters in the request body instead of the query string.
     * Regression test: fromRequest() used to hardcode $request->query, so a POST-configured
     * table always resolved zero columns.
     */
    #[Test]
    public function it_parses_from_a_post_request_body(): void
    {
        $request = Request::create('/ajax', 'POST', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true],
            ],
        ]);

        $columns = Columns::fromRequest($request);

        $this->assertNotNull($columns->getColumnByName('name'));
    }
}
