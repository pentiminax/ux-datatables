<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

use Pentiminax\UX\DataTables\Contracts\ExecutableActionInterface;

final readonly class ActionPermissionContext
{
    private function __construct(
        public string $dataTableClass,
        public ExecutableActionInterface $action,
        public mixed $currentSource,
        public bool $hasRowContext,
    ) {
    }

    /**
     * Builds the context of an action checked outside of any row, such as a toolbar
     * button or a bulk action: only its static permission can be decided.
     */
    public static function forTable(?string $dataTableClass, ExecutableActionInterface $action): self
    {
        return new self(
            dataTableClass: $dataTableClass ?? '',
            action: $action,
            currentSource: null,
            hasRowContext: false,
        );
    }

    /**
     * Builds the context of an action checked against the row it would act on, so a
     * permission subject resolver can vote on that row.
     */
    public static function forRow(?string $dataTableClass, ExecutableActionInterface $action, mixed $row): self
    {
        return new self(
            dataTableClass: $dataTableClass ?? '',
            action: $action,
            currentSource: $row,
            hasRowContext: true,
        );
    }
}
