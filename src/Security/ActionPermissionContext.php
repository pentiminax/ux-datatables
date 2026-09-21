<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

use Pentiminax\UX\DataTables\Contracts\ExecutableActionInterface;

final readonly class ActionPermissionContext
{
    public function __construct(
        public string $dataTableClass,
        public ExecutableActionInterface $action,
        public mixed $currentSource,
        public bool $hasRowContext,
    ) {
    }
}
