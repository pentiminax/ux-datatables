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
        return new self(
            value: $request->query->get('search[value]'),
            regex: $request->query->get('search[regex]') === 'true',
        );
    }
}
