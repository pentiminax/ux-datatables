<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

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
        $this->permissionChecker = $permissionChecker ?? AuthorizationChecker::fallbackFor(self::class);
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

    private static function twigRequired(): AjaxActionResult
    {
        return AjaxActionResult::badRequest('Twig is required to render a detail row.');
    }

    private function renderTemplate(Environment $twig, Action $action, object $entity): AjaxActionResult
    {
        $parameters = array_merge(['entity' => $entity], $action->getCollapsibleParameters());

        return AjaxActionResult::success($twig->render($action->getCollapsibleTemplate(), $parameters));
    }
}
