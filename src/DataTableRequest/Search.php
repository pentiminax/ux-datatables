<?php

declare(strict_types=1);

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
        $value = $data['value'] ?? null;

        return new self(
            value: \is_scalar($value) ? (string) $value : null,
            regex: 'true' === ($data['regex'] ?? null),
        );
    }

    public static function fromRequest(Request $request): self
    {
        $search = RequestInputBag::resolve($request)->all('search');
        $regex  = isset($search['regex']) && 'true' === $search['regex'];

        return new self(
            value: $search['value'] ?? null,
            regex: $regex,
        );
    }
}
