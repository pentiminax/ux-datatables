<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Twig\Environment;

final readonly class DetailRowService
{
    private AuthorizationChecker $permissionChecker;

    public function __construct(
        private EntityLocator $locator,
        private RowScopeGuard $scopeGuard,
        private ?Environment $twig = null,
        ?AuthorizationChecker $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new AuthorizationChecker();
    }

    /**
     * @internal
     */
    public function render(AuthorizedRow $row): AjaxActionResult
    {
        if (null === $this->twig) {
            return self::twigRequired();
        }

        $action = $row->action ?? throw new \LogicException('A detail row needs its action.');

        return $this->renderTemplate($this->twig, $action, $row->context->entity);
    }

    /**
     * @deprecated since 1.5, use render() instead. Removed in 2.0.
     */
    public function handleView(ResolvedDataTable $dataTable, int|string $id): AjaxActionResult
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.5', 'The "%s()" method is deprecated, use "render()" instead.', __METHOD__);

        if (null === $this->twig) {
            return self::twigRequired();
        }

        $action = $dataTable->findAction(ActionType::Detail, collapsible: true);

        if (null === $action) {
            return AjaxActionResult::forbidden();
        }

        if (!$this->permissionChecker->canExecuteAction($dataTable->dataTableClass, $action)) {
            return AjaxActionResult::forbidden();
        }

        try {
            $this->scopeGuard->assertInScope($dataTable, $id);
            $context = $this->locator->locate($dataTable->requireEntityClass(), $id);
        } catch (EntityNotFoundException) {
            return AjaxActionResult::notFound();
        }

        if (!$this->isGranted($dataTable, $action, $context->entity)) {
            return AjaxActionResult::forbidden();
        }

        return $this->renderTemplate($this->twig, $action, $context->entity);
    }

    private static function twigRequired(): AjaxActionResult
    {
        return AjaxActionResult::badRequest('Twig is required to render a detail row.');
    }

    private function renderTemplate(Environment $twig, Action $action, object $entity): AjaxActionResult
    {
        $parameters = array_merge(['entity' => $entity], $action->getCollapsibleParameters());

        return AjaxActionResult::success($twig->render($action->getCollapsibleTemplate(), $parameters));
    }

    /**
     * Mirrors the permissions the rendering pipeline evaluates for this action, so
     * an action the user cannot see is also an action they cannot fetch.
     */
    private function isGranted(ResolvedDataTable $dataTable, Action $action, object $entity): bool
    {
        return $this->permissionChecker->canViewRowDetails($entity)
            && $this->permissionChecker->canExecuteActionOnRow($dataTable->dataTableClass, $action, $entity);
    }
}
