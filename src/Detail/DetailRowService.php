<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Detail;

use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use Twig\Environment;

final readonly class DetailRowService
{
    private PermissionChecker $permissionChecker;

    public function __construct(
        private EntityLocator $locator,
        private ?Environment $twig = null,
        ?PermissionChecker $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new PermissionChecker();
    }

    public function handleView(ResolvedDataTable $dataTable, int|string $id): DetailRowResult
    {
        if (null === $this->twig) {
            return DetailRowResult::badRequest('Twig is required to render a detail row.');
        }

        $action = $this->resolveCollapsibleDetailAction($dataTable->table);

        if (null === $action) {
            return DetailRowResult::badRequest('No collapsible detail action is configured for this DataTable.');
        }

        try {
            $context = $this->locator->locate($dataTable->requireEntityClass(), $id);
        } catch (EntityNotFoundException) {
            return DetailRowResult::notFound();
        }

        if (!$this->permissionChecker->isGranted('VIEW', $context->entity)) {
            return DetailRowResult::forbidden();
        }

        $parameters = array_merge(['entity' => $context->entity], $action->getCollapsibleParameters());

        return DetailRowResult::success($this->twig->render($action->getCollapsibleTemplate(), $parameters));
    }

    private function resolveCollapsibleDetailAction(AbstractDataTable $dataTable): ?Action
    {
        foreach ($dataTable->getConfiguredDataTable()->getColumns() as $column) {
            if (!$column instanceof ActionsProvidingColumnInterface) {
                continue;
            }

            foreach ($column->getActions()?->getActions() ?? [] as $action) {
                if (ActionType::Detail === $action->getType() && $action->isCollapsible()) {
                    return $action;
                }
            }
        }

        return null;
    }
}
