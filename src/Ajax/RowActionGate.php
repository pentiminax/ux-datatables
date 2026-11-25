<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Contracts\ExecutableActionInterface;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContext;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\RequestQueryHydrator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\Runtime\DataTableCall;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
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
        private readonly DataTableRuntime $runtime,
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
            return $this->authorizeToggle($request, $rowAction);
        }

        [$table, $action] = $this->authorizeAction(
            $rowAction->dataTable,
            fn (ResolvedDataTable $table): ?Action => $this->findRowAction($table, $rowAction->kind),
        );

        $this->scopeGuard->assertInScope($table, fn (): DataTableCall => $this->scopeCall($request, $table, $rowAction), $rowAction->id);

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

    /**
     * Runs CSRF, table and action lookup, then the static permission, all before the row scope
     * capability is resolved and before any entity is loaded.
     *
     * @throws MutationNotAllowedException when the action is absent or a permission denies it
     */
    public function authorizeBulk(Request $request, BulkActionRequest $bulk): AuthorizedBulk
    {
        $this->tokenValidator->validate($request);

        [$table, $action] = $this->authorizeAction(
            $bulk->dataTable,
            static fn (ResolvedDataTable $table): ?BulkAction => $table->findBulkAction($bulk->action),
        );

        $this->scopeGuard->scopeChecker($table);

        return new AuthorizedBulk(
            table: $table,
            action: $action,
            bind: function () use ($request, $table, $bulk): DataTableCall {
                $displayed = $request->duplicate();

                RequestQueryHydrator::hydrate($displayed, $bulk->query);

                return $this->runtime->handle($table->table, $displayed);
            },
        );
    }

    /**
     * @template T of ExecutableActionInterface
     *
     * @param \Closure(ResolvedDataTable):?T $find
     *
     * @return array{ResolvedDataTable, T}
     */
    private function authorizeAction(string $dataTable, \Closure $find): array
    {
        $table  = $this->registry->resolveAction($dataTable);
        $action = $find($table) ?? throw new MutationNotAllowedException();

        if (!$this->permissionChecker->canExecuteAction($table->dataTableClass, $action)) {
            throw new MutationNotAllowedException();
        }

        return [$table, $action];
    }

    private function authorizeToggle(Request $request, RowActionRequest $rowAction): AuthorizedRow
    {
        $toggle = $this->toggleResolver->resolve($rowAction->dataTable, (string) $rowAction->field);
        $table  = $toggle->table ?? throw new \LogicException('The boolean mutation context must carry its resolved table.');

        $this->assertToggleInScope($request, $table, $toggle, $rowAction);

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

    private function assertToggleInScope(Request $request, ResolvedDataTable $table, BooleanMutationContext $toggle, RowActionRequest $rowAction): void
    {
        if (null === $table->entityClass) {
            throw new EntityNotFoundException();
        }

        if ($toggle->entityClass === $table->entityClass) {
            $this->scopeGuard->assertInScope($table, fn (): DataTableCall => $this->scopeCall($request, $table, $rowAction), $rowAction->id);

            return;
        }

        if (null === $rowAction->rowId) {
            throw new EntityNotFoundException();
        }

        $this->scopeGuard->assertRelatedRowOwns(
            table: $table,
            call: fn (): DataTableCall => $this->scopeCall($request, $table, $rowAction),
            rowId: $rowAction->rowId,
            idPath: $toggle->toggleIdField,
            targetId: $rowAction->id,
        );
    }

    /**
     * The table bound to a copy of the request carrying the forwarded parameters it declares, so
     * its query builder reads them while the incoming request stays untouched.
     */
    private function scopeCall(Request $request, ResolvedDataTable $table, RowActionRequest $rowAction): DataTableCall
    {
        $names     = $table->definition->table->getForwardedQueryParameters();
        $scoped    = $request->duplicate();
        $forwarded = array_intersect_key($rowAction->query, array_flip($names));

        RequestQueryHydrator::hydrate($scoped, $forwarded);

        return $this->runtime->handle($table->table, $scoped);
    }

    private function findRowAction(ResolvedDataTable $table, RowActionKind $kind): ?Action
    {
        return match ($kind) {
            RowActionKind::Delete => $table->findAction(ActionType::Delete),
            RowActionKind::Detail => $table->findAction(ActionType::Detail, collapsible: true),
            default               => $table->findAction(ActionType::Edit),
        };
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
