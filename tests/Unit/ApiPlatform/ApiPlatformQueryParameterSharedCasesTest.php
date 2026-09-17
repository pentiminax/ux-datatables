<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformQueryParameterFactory;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column as RequestColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Replays the shared translation cases the TypeScript adapter replays in
 * assets/test/apiPlatformQueryParameterParity.test.ts. A case failing on one side only means the
 * two implementations have drifted: a table read server-side would then query the API differently
 * from the same table read by the browser.
 *
 * @internal
 */
#[CoversClass(ApiPlatformQueryParameterFactory::class)]
final class ApiPlatformQueryParameterSharedCasesTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../Fixtures/api-platform-query-parameters.json';

    #[Test]
    #[DataProvider('provideSharedCases')]
    public function it_translates_a_shared_case(array $params, array $expected): void
    {
        $columns = self::configuredColumns();
        $request = self::request($params);
        $intent  = (new DefaultDataTableQueryIntentFactory())->create($request, $columns);

        $this->assertSame($expected, (new ApiPlatformQueryParameterFactory())->create($intent, $request));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: array<string, string>}>
     */
    public static function provideSharedCases(): iterable
    {
        foreach (self::fixture()['cases'] as $case) {
            yield $case['name'] => [$case['params'], $case['expected']];
        }
    }

    /**
     * @return array{columns: list<array<string, string>>, cases: list<array<string, mixed>>}
     */
    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<ColumnInterface>
     */
    private static function configuredColumns(): array
    {
        $columns = [];

        foreach (self::fixture()['columns'] as $column) {
            $columns[] = TextColumn::new($column['name'], $column['name'])->setField($column['field']);
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function request(array $params): DataTableRequest
    {
        $requestColumns = [];

        foreach ($params['columns'] ?? [] as $column) {
            $name                  = $column['name'];
            $requestColumns[$name] = new RequestColumn(
                data: $column['data'] ?? $name,
                name: $name,
                searchable: true,
                orderable: true,
                search: new Search(value: $column['search']['value'] ?? null, regex: false),
            );
        }

        $columns = new Columns($requestColumns);

        $order = [];
        foreach ($params['order'] ?? [] as $entry) {
            $order[] = Order::fromArray($entry, $columns);
        }

        return new DataTableRequest(
            draw: 1,
            columns: $columns,
            start: $params['start'],
            length: $params['length'],
            search: new Search(value: $params['search']['value'] ?? null, regex: false),
            order: $order,
            filters: $params['filters'] ?? [],
        );
    }
}
