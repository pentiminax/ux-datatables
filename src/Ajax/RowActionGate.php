<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContext;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class RowActionGate
{
    public function __construct(
        private readonly MutationTokenValidator $tokenValidator,
        private readonly AjaxDataTableRegistry $registry,
        private readonly BooleanMutationContextResolver $toggleResolver,
        private readonly RowScopeGuard $scopeGuard,
        private readonly EntityLocator $locator,
        private readonly AuthorizationChecker $permissionChecker,
    ) {
    }

    /**
     * Runs CSRF (mutating kinds only), table and action lookup, static permission, row scope,
     * entity lookup, then row permission. A row outside the row scope is answered as not found,
     * before its permission is looked at.
     *
     * @throws EntityNotFoundException     when the row is out of scope or missing
     * @throws MutationNotAllowedException when the action is absent or a permission denies it
     */
    public function authorize(Request $request, RowActionRequest $rowAction): AuthorizedRow
    {
        if ($rowAction->kind->mutates()) {
            $this->tokenValidator->validate($request);
        }

        if (RowActionKind::Toggle === $rowAction->kind) {
            return $this->authorizeToggle($rowAction);
        }

        $table  = $this->registry->resolveAction($rowAction->dataTable);
        $action = $this->resolveAction($table, $rowAction->kind);

        if (!$this->permissionChecker->canExecuteAction($table->dataTableClass, $action)) {
            throw new MutationNotAllowedException();
        }

        $this->scopeGuard->assertInScope($table, $rowAction->id);

        $context = $this->locator->locate($table->requireEntityClass(), $rowAction->id);

        if (!$this->isRowGranted($rowAction->kind, $table, $action, $context->entity)) {
            throw new MutationNotAllowedException();
        }

        return new AuthorizedRow(
            table: $table,
            action: $action,
            context: $context,
            id: $rowAction->id,
        );
    }

    private function authorizeToggle(RowActionRequest $rowAction): AuthorizedRow
    {
        $toggle = $this->toggleResolver->resolve($rowAction->dataTable, (string) $rowAction->field);
        $table  = $toggle->table ?? throw new \LogicException('The boolean mutation context must carry its resolved table.');

        $this->assertToggleInScope($table, $toggle, $rowAction);

        $context = $this->locator->locate($toggle->entityClass, $rowAction->id);

        if (!$this->permissionChecker->canEditRow($context->entity)) {
            throw new MutationNotAllowedException();
        }

        return new AuthorizedRow(
            table: $table,
            action: null,
            context: $context,
            id: $rowAction->id,
            field: $toggle->field,
            entityClass: $toggle->entityClass,
        );
    }

    private function assertToggleInScope(ResolvedDataTable $table, BooleanMutationContext $toggle, RowActionRequest $rowAction): void
    {
        if (null === $table->entityClass) {
            trigger_deprecation('pentiminax/ux-datatables', '1.5', 'Toggling a boolean column on table "%s", which has no entity class, skips the row scope check: 2.0 refuses the request. Give the table an entity class with #[AsDataTable(entityClass: ...)].', $table->dataTableClass);

            return;
        }

        if ($toggle->entityClass === $table->entityClass) {
            $this->scopeGuard->assertInScope($table, $rowAction->id);

            return;
        }

        if (null === $rowAction->rowId) {
            throw new EntityNotFoundException();
        }

        $this->scopeGuard->assertRelatedRowOwns($table, $rowAction->rowId, $toggle->toggleIdField, $rowAction->id);
    }

    private function resolveAction(ResolvedDataTable $table, RowActionKind $kind): Action
    {
        $action = match ($kind) {
            RowActionKind::Delete => $table->findAction(ActionType::Delete),
            RowActionKind::Detail => $table->findAction(ActionType::Detail, collapsible: true),
            default               => $table->findAction(ActionType::Edit),
        };

        return $action ?? throw new MutationNotAllowedException();
    }

    private function isRowGranted(RowActionKind $kind, ResolvedDataTable $table, Action $action, object $entity): bool
    {
        $kindGranted = match ($kind) {
            RowActionKind::Delete => $this->permissionChecker->canDeleteRow($entity),
            RowActionKind::Detail => $this->permissionChecker->canViewRowDetails($entity),
            default               => $this->permissionChecker->canEditRow($entity),
        };

        return $kindGranted && $this->permissionChecker->canExecuteActionOnRow($table->dataTableClass, $action, $entity);
    }
}
