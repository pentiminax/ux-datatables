<?php

namespace Pentiminax\UX\DataTables\Model;

use Symfony\Component\HttpFoundation\Request;

final readonly class Search
{
    public function __construct(
        public ?string $search,
        public bool $regex,
    ) {
    }

    public static function fromRequest(Request $request): Search
    {
        if ($request->isMethod('GET')) {
            $search = $request->query->all('search');
        } else {
            $search = $request->request->all('search');
        }

        return new self(
            search: $search['value'] ?? '',
            regex: isset($search['regex']) && 'true' === $search['regex']
        );
    }
}
