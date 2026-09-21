<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Symfony\Component\ExpressionLanguage\Expression;

/**
 * An action the user triggers and the bundle authorizes through
 * {@see \Pentiminax\UX\DataTables\Security\Permission::DT_EXECUTE_ACTION}.
 *
 * Implemented by row actions ({@see \Pentiminax\UX\DataTables\Model\Action}) and by bulk actions
 * ({@see \Pentiminax\UX\DataTables\Model\BulkAction}), so both travel through the same
 * {@see \Pentiminax\UX\DataTables\Security\ActionPermissionContext} and the same voter.
 */
interface ExecutableActionInterface
{
    public function getName(): string;

    public function getPermission(): string|Expression|null;

    public function getPermissionSubjectResolver(): ?\Closure;

    /**
     * A permission that can be evaluated before any row exists.
     */
    public function hasStaticPermission(): bool;

    /**
     * A permission that needs the row as its subject.
     */
    public function hasPerRowPermission(): bool;
}
