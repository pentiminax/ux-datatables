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

    public static function fromArray(array $data): ?self
    {
        $value = $data['value'] ?? '';

        if ($value === '' || $value === null) {
            return null;
        }

        return new self(
            value: $value,
            regex: filter_var($data['regex'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public static function fromRequest(Request $request): Search
    {
        if ($request->isMethod('GET')) {
            $search = $request->query->all('search');
        } else {
            $search = $request->request->all('search');
        }

        return new self(
            value: $search['value'] ?? '',
            regex: filter_var($search['regex'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
    }
}
