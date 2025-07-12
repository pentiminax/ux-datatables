<?php

namespace Pentiminax\UX\DataTables\Model\Options;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Model\DataTable;

class LayoutOption implements \JsonSerializable
{
    public function __construct(
        public readonly DataTable $table,
        public readonly Feature $topStart = Feature::PAGE_LENGTH,
        public readonly Feature $topEnd = Feature::SEARCH,
        public readonly Feature $bottomStart = Feature::INFO,
        public readonly Feature $bottomEnd = Feature::PAGING,
    ) {
    }

    public function jsonSerialize(): array
    {
        $array = [
            'topStart' => $this->topStart->value,
            'topEnd' => $this->topEnd->value,
            'bottomStart' => $this->bottomStart->value,
            'bottomEnd' => $this->bottomEnd->value,
        ];

        foreach (['topStart', 'topEnd', 'bottomStart', 'bottomEnd'] as $position) {
            if ($array[$position] === 'paging') {
                $array[$position] = [
                    'paging' => $this->table->getOption('paging') ?? [],
                ];
                break;
            }
        }

        return $array;
    }
}