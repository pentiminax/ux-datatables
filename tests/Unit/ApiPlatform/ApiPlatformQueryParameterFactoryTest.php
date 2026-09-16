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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApiPlatformQueryParameterFactory::class)]
final class ApiPlatformQueryParameterFactoryTest extends TestCase
{
    #[Test]
    public function it_turns_offset_and_length_into_a_page_number(): void
    {
        $parameters = $this->create($this->request(start: 20, length: 10));

        $this->assertSame('3', $parameters['page']);
        $this->assertSame('10', $parameters['itemsPerPage']);
    }

    #[Test]
    public function it_omits_pagination_when_the_request_carries_no_length(): void
    {
        $parameters = $this->create($this->request()->withoutPagination());

        $this->assertArrayNotHasKey('page', $parameters);
        $this->assertArrayNotHasKey('itemsPerPage', $parameters);
    }

    #[Test]
    public function it_trims_the_global_search_into_the_q_parameter(): void
    {
        $parameters = $this->create($this->request(search: '  alice  '));

        $this->assertSame('alice', $parameters['q']);
    }

    #[Test]
    public function it_omits_the_q_parameter_when_the_global_search_is_blank(): void
    {
        $parameters = $this->create($this->request(search: '   '));

        $this->assertArrayNotHasKey('q', $parameters);
    }

    #[Test]
    public function it_keeps_every_ordered_column_in_priority_order(): void
    {
        $parameters = $this->create($this->request(order: [
            new Order(column: 1, dir: 'desc', name: 'createdAt'),
            new Order(column: 0, dir: 'DESC', name: 'email'),
        ]));

        $this->assertSame(
            ['order[user.createdAt]' => 'desc', 'order[user.email]' => 'desc'],
            array_filter($parameters, static fn (string $key): bool => str_starts_with($key, 'order['), \ARRAY_FILTER_USE_KEY),
        );
    }

    #[Test]
    public function it_falls_back_to_ascending_for_an_unknown_direction(): void
    {
        $parameters = $this->create($this->request(order: [new Order(column: 0, dir: 'sideways', name: 'email')]));

        $this->assertSame('asc', $parameters['order[user.email]']);
    }

    #[Test]
    public function it_resolves_the_ordered_column_by_name_not_by_index(): void
    {
        // The Select extension unshifts a checkbox column the server never configured, so the
        // request index no longer matches the configured position.
        $parameters = $this->create($this->request(order: [new Order(column: 2, dir: 'asc', name: 'createdAt')]));

        $this->assertSame('asc', $parameters['order[user.createdAt]']);
    }

    #[Test]
    public function it_ignores_an_order_on_an_unknown_column(): void
    {
        $parameters = $this->create($this->request(order: [new Order(column: 9, dir: 'asc', name: 'ghost')]));

        $this->assertSame(['page', 'itemsPerPage'], array_keys($parameters));
    }

    #[Test]
    public function it_maps_a_column_search_onto_the_column_field(): void
    {
        $parameters = $this->create($this->request(columnSearches: ['email' => 'user@example.com']));

        $this->assertSame('user@example.com', $parameters['user.email']);
    }

    #[Test]
    public function it_flattens_a_scalar_filter(): void
    {
        $parameters = $this->create($this->request(filters: ['status' => 'active', 'blank' => '  ']));

        $this->assertSame('active', $parameters['status']);
        $this->assertArrayNotHasKey('blank', $parameters);
    }

    #[Test]
    public function it_indexes_a_list_filter_without_leaving_gaps(): void
    {
        $parameters = $this->create($this->request(filters: ['roles' => ['ROLE_A', '', 'ROLE_B']]));

        $this->assertSame('ROLE_A', $parameters['roles[0]']);
        $this->assertSame('ROLE_B', $parameters['roles[1]']);
        $this->assertArrayNotHasKey('roles[2]', $parameters);
    }

    #[Test]
    public function it_maps_a_range_filter_onto_date_filter_bounds(): void
    {
        $parameters = $this->create($this->request(filters: [
            'createdAt' => ['from' => '2026-01-01', 'to' => '2026-01-31'],
        ]));

        $this->assertSame('2026-01-01', $parameters['createdAt[after]']);
        $this->assertSame('2026-01-31', $parameters['createdAt[before]']);
    }

    #[Test]
    public function it_drops_a_filter_shape_it_cannot_express(): void
    {
        $parameters = $this->create($this->request(filters: ['weird' => ['nested' => ['deep' => 1]]]));

        $this->assertArrayNotHasKey('weird', $parameters);
        $this->assertArrayNotHasKey('weird[nested]', $parameters);
    }

    #[Test]
    public function it_refuses_to_let_a_filter_overwrite_a_protocol_parameter(): void
    {
        $parameters = $this->create($this->request(
            start: 20,
            length: 10,
            search: 'alice',
            filters: ['page' => '99', 'itemsPerPage' => '5000', 'q' => 'injected'],
        ));

        $this->assertSame('3', $parameters['page']);
        $this->assertSame('10', $parameters['itemsPerPage']);
        $this->assertSame('alice', $parameters['q']);
    }

    #[Test]
    public function it_refuses_to_let_a_filter_overwrite_a_column_search(): void
    {
        $parameters = $this->create($this->request(
            columnSearches: ['email' => 'from-column'],
            filters: ['user.email' => 'from-filter'],
        ));

        $this->assertSame('from-column', $parameters['user.email']);
    }

    /**
     * @return array<string, string|array<int|string, string>>
     */
    private function create(DataTableRequest $request): array
    {
        $columns = $this->columns();
        $intent  = (new DefaultDataTableQueryIntentFactory())->create($request, $columns);

        return (new ApiPlatformQueryParameterFactory())->create($intent, $request);
    }

    /**
     * @return list<ColumnInterface>
     */
    private function columns(): array
    {
        return [
            TextColumn::new('email', 'Email')->setField('user.email'),
            TextColumn::new('createdAt', 'Created at')->setField('user.createdAt'),
        ];
    }

    /**
     * @param list<Order>           $order
     * @param array<string, string> $columnSearches
     * @param array<string, mixed>  $filters
     */
    private function request(
        int $start = 0,
        int $length = 10,
        ?string $search = null,
        array $order = [],
        array $columnSearches = [],
        array $filters = [],
    ): DataTableRequest {
        $requestColumns = [];

        foreach (['email', 'createdAt'] as $index => $name) {
            $requestColumns[$name] = new RequestColumn(
                data: $name,
                name: $name,
                searchable: true,
                orderable: true,
                search: \array_key_exists($name, $columnSearches)
                    ? new Search(value: $columnSearches[$name], regex: false)
                    : null,
            );
        }

        return new DataTableRequest(
            draw: 1,
            columns: new Columns($requestColumns),
            start: $start,
            length: $length,
            search: new Search(value: $search, regex: false),
            order: $order,
            filters: $filters,
        );
    }
}
