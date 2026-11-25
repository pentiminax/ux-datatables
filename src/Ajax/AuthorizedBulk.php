<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Runtime\DataTableCall;

/**
 * @internal
 */
final class AuthorizedBulk
{
    private ?DataTableCall $call = null;

    /**
     * @param \Closure():DataTableCall $bind binds the table to the displayed request; run at most once
     */
    public function __construct(
        public readonly ResolvedDataTable $table,
        public readonly BulkAction $action,
        private readonly \Closure $bind,
    ) {
    }

    public function call(): DataTableCall
    {
        return $this->call ??= ($this->bind)();
    }
}
