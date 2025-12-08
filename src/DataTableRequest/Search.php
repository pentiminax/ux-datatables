<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

use Symfony\Component\HttpFoundation\Request;

final readonly class Search
{
    public function __construct(
        public ?string $value,
        public bool $regex,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            value: $data['value'],
            regex: $data['regex'],
        );
    }

    public static function fromRequest(Request $request): self
    {
        $search = $request->query->all('search');

        return new self(
            value: $search['value'],
            regex: $search['regex'] === 'true',
        );
    }
}
