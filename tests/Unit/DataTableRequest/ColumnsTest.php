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

    /**
     * Select's checkbox column is unshifted with name/data null. The export POST flattener
     * used to omit those keys, and fromArray() TypeError'd on the non-string constructor args.
     */
    #[Test]
    public function it_parses_a_checkbox_column_with_omitted_name_and_data(): void
    {
        $request = Request::create('/datatables/ajax/export', 'POST', [
            'columns' => [
                ['searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ]);

        $columns = Columns::fromRequest($request);

        $checkbox = $columns->getColumnByIndex(0);
        $this->assertNotNull($checkbox);
        $this->assertSame('', $checkbox->name);
        $this->assertSame('', $checkbox->data);
        $this->assertFalse($checkbox->searchable);
        $this->assertFalse($checkbox->orderable);
        $this->assertNotNull($columns->getColumnByName('email'));
    }

    #[Test]
    public function it_treats_boolean_true_flags_the_same_as_the_string_true(): void
    {
        $request = Request::create('/ajax', 'POST', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true],
            ],
        ]);

        $column = Columns::fromRequest($request)->getColumnByName('name');

        $this->assertNotNull($column);
        $this->assertTrue($column->searchable);
        $this->assertTrue($column->orderable);
    }
}
