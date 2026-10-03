<?php

declare(strict_types=1);

namespace App\Demo;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;

final readonly class Page
{
    /**
     * @param class-string<AbstractDataTable> $table
     * @param list<string>                    $features API names shown as chips
     * @param list<string>                    $sources  extra project files shown in the code panel
     */
    public function __construct(
        public string $route,
        public string $group,
        public string $table,
        public string $docs,
        public array $features,
        public array $sources = [],
    ) {
    }
}
