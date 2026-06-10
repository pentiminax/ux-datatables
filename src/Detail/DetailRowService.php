<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Detail;

use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Dto\AjaxDetailQueryDto;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Psr\Container\ContainerInterface;
use Twig\Environment;

final readonly class DetailRowService
{
    public function __construct(
        private ContainerInterface $dataTables,
        private EntityLocator $locator,
        private ?Environment $twig = null,
    ) {
    }

    public function handleView(AjaxDetailQueryDto $payload): DetailRowResult
    {
        if (null === $this->twig) {
            return DetailRowResult::badRequest('Twig is required to render a detail row.');
        }

        if (null === $payload->dataTableClass) {
            return DetailRowResult::badRequest('A detail row requires a DataTable class (AbstractDataTable).');
        }

        $action = $this->resolveCollapsibleDetailAction($payload->dataTableClass);

        if (null === $action) {
            return DetailRowResult::badRequest('No collapsible detail action is configured for this DataTable.');
        }

        try {
            $context = $this->locator->locate($payload->entity, $payload->id);
        } catch (EntityNotFoundException) {
            return DetailRowResult::notFound();
        }

        $html = $this->twig->render(
            (string) $action->getCollapsibleTemplate(),
            ['entity' => $context->entity] + $action->getCollapsibleParameters(),
        );

        return DetailRowResult::success($html);
    }

    private function resolveCollapsibleDetailAction(string $dataTableClass): ?Action
    {
        if (!$this->dataTables->has($dataTableClass)) {
            return null;
        }

        $dataTable = $this->dataTables->get($dataTableClass);

        if (!$dataTable instanceof AbstractDataTable) {
            return null;
        }

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
