<?php

namespace Pentiminax\UX\DataTables\Model;

use Symfony\Component\HttpFoundation\Request;

final readonly class DataTableQuery
{
    public function __construct(
        public ?int $draw,
        public int $start = 0,
        public int $length = 10,
        public ?Search $search = null,

        /** @var Order[] */
        public array $orders = []
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $orders = [];
        foreach ($request->query->all('order') as $order) {
            $orders[] = Order::fromArray($order);
        }

        return new self(
            draw: $request->query->get('draw'),
            start: $request->query->get('start', 0),
            length: $request->query->get('length', 10),
            search: Search::fromRequest($request),
            orders: $orders
        );
    }
}
