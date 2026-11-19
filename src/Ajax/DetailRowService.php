<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Model\Action;
use Twig\Environment;

final readonly class DetailRowService
{
    public function __construct(
        private ?Environment $twig = null,
    ) {
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
