<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

use Pentiminax\UX\DataTables\Model\Action;

final readonly class ActionPermissionContext
{
    public function __construct(
        public string $dataTableClass,
        public Action $action,
        public mixed $currentSource,
        public bool $hasRowContext,
    ) {
    }
}
