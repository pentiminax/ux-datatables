<?php

declare(strict_types=1);

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

        /** @var array<string, mixed> User-facing filter values keyed by filter name. */
        public array $filters = [],
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $bag     = RequestInputBag::resolve($request);
        $columns = Columns::fromRequest($request);

        $orders = [];
        foreach ($bag->all('order') as $orderData) {
            $orders[] = Order::fromArray($orderData, $columns);
        }

        return new self(
            draw: $bag->getInt('draw'),
            columns: $columns,
            start: $bag->getInt('start'),
            length: $bag->getInt('length'),
            search: Search::fromRequest($request),
            order: $orders,
            filters: $bag->all('filters'),
        );
    }

    /**
     * Requested page size, falling back to $default when DataTables sent 0 or less
     * (its "show all" length, which providers built on LIMIT/OFFSET can't honor).
     */
    public function pageLength(int $default = 25): int
    {
        return $this->length > 0 ? $this->length : $default;
    }

    /**
     * Trimmed global search term, or null when empty/absent.
     *
     * Tests for emptiness explicitly rather than via a falsy check, so a search
     * for the literal string "0" is preserved instead of being treated as empty.
     */
    public function searchTerm(): ?string
    {
        $value = trim(($this->search?->value ?? ''));

        return '' !== $value ? $value : null;
    }
}
