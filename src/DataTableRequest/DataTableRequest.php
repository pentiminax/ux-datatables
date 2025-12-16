<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

use Symfony\Component\HttpFoundation\Request;

final readonly class DataTableRequest
{
    public function __construct(
        public ?int $draw,
        public int $start = 0,
        public int $length = 10,
        public Columns $columns,

        public ?Search $search = null,

        /** @var Order[] */
        public array $order = [],
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            draw: $request->query->getInt('draw'),
            start: $request->query->getInt('start'),
            length: $request->query->getInt('length'),
            columns: Columns::fromRequest($request),
            search: Search::fromRequest($request),
            order: $request->query->all('order')
        );
    }
}
