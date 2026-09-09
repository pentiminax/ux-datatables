<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Twig\Environment;

final readonly class DetailRowService
{
    private AuthorizationChecker $permissionChecker;

    public function __construct(
        private EntityLocator $locator,
        private ?Environment $twig = null,
        ?AuthorizationChecker $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new AuthorizationChecker();
    }

    public function handleView(ResolvedDataTable $dataTable, int|string $id): AjaxActionResult
    {
        if (null === $this->twig) {
            return AjaxActionResult::badRequest('Twig is required to render a detail row.');
        }

        $action = $dataTable->findAction(ActionType::Detail, collapsible: true);

        if (null === $action) {
            return AjaxActionResult::badRequest('No collapsible detail action is configured for this DataTable.');
        }

        if (!$this->isActionGranted($dataTable, $action, null, false)) {
            return AjaxActionResult::forbidden();
        }

        try {
            $context = $this->locator->locate($dataTable->requireEntityClass(), $id);
        } catch (EntityNotFoundException) {
            return AjaxActionResult::notFound();
        }

        if (!$this->isGranted($dataTable, $action, $context->entity)) {
            return AjaxActionResult::forbidden();
        }

        $parameters = array_merge(['entity' => $context->entity], $action->getCollapsibleParameters());

        return AjaxActionResult::success($this->twig->render($action->getCollapsibleTemplate(), $parameters));
    }

    /**
     * Mirrors the permissions the rendering pipeline evaluates for this action, so
     * an action the user cannot see is also an action they cannot fetch.
     */
    private function isGranted(ResolvedDataTable $dataTable, Action $action, object $entity): bool
    {
        return $this->permissionChecker->isGranted(Permission::DT_VIEW_ROW_DETAILS, $entity)
            && $this->isActionGranted($dataTable, $action, $entity, $action->hasPerRowPermission());
    }

    private function isActionGranted(ResolvedDataTable $dataTable, Action $action, mixed $source, bool $hasRowContext): bool
    {
        return $this->permissionChecker->isGranted(Permission::DT_EXECUTE_ACTION, new ActionPermissionContext(
            $dataTable->dataTableClass,
            $action,
            $source,
            $hasRowContext,
        ));
    }
}
