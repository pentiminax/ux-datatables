<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

use Symfony\Component\HttpFoundation\Request;

final readonly class DataTableRequest
{
    public function __construct(
        public ?int $draw,
        public Columns $columns,
        public int $start = 0,
        public int $length = 10,
        public ?Search $search = null,

        /** @var Order[] */
        public array $order = [],
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $columns = Columns::fromRequest($request);

        $orders = [];
        foreach ($request->query->all('order') as $orderData) {
            $orders[] = Order::fromArray($orderData, $columns);
        }

        return new self(
            draw: $request->query->getInt('draw'),
            columns: $columns,
            start: $request->query->getInt('start'),
            length: $request->query->getInt('length'),
            search: Search::fromRequest($request),
            order: $orders,
        );
    }
}
