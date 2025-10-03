<?php

namespace Pentiminax\UX\DataTables\Model;

use Symfony\Component\HttpFoundation\Request;

final readonly class DataTableQuery
{
    public function __construct(
        public ?int $draw,
        public int $start = 0,
        public int $length = 10,
        public ?Search $search = null
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            draw: $request->get('draw'),
            start: $request->get('start', 0),
            length: $request->get('length', 10),
            search: Search::fromRequest($request)
        );
    }
}