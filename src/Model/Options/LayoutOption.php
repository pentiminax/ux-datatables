<?php

namespace Pentiminax\UX\DataTables\Model\Options;

use Pentiminax\UX\DataTables\Enum\Feature;

class LayoutOption implements \JsonSerializable
{
    public function __construct(
        public readonly Feature $topStart = Feature::PAGE_LENGTH,
        public readonly Feature $topEnd = Feature::SEARCH,
        public readonly Feature $bottomStart = Feature::INFO,
        public readonly Feature $bottomEnd = Feature::PAGING,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'topStart' => $this->topStart->value,
            'topEnd' => $this->topEnd->value,
            'bottomStart' => $this->bottomStart->value,
            'bottomEnd' => $this->bottomEnd->value,
        ];
    }
}