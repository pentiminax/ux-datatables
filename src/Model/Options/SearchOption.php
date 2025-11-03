<?php

namespace Pentiminax\UX\DataTables\Model\Options;

final readonly class SearchOption implements \JsonSerializable
{
    private function __construct(
        public bool $caseInsensitive,
        public bool $regex,
        public bool $return,
        public ?string $search,
        public bool $smart,
        public ?int $searchDelay,
    ) {
    }

    public static function new(bool $caseInsensitive = true, bool $regex = false, bool $return = false, ?string $search = null, bool $smart = true, ?int $searchDelay = null): self
    {
        return new self(
            caseInsensitive: $caseInsensitive,
            regex: $regex,
            return: $return,
            search: $search,
            smart: $smart,
            searchDelay: $searchDelay,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'caseInsensitive' => $this->caseInsensitive,
            'regex'           => $this->regex,
            'return'          => $this->return,
            'search'          => $this->search,
            'smart'           => $this->smart,
            'searchDelay'     => $this->searchDelay,
        ];
    }
}
