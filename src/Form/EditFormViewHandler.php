<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;

final class EditFormViewHandler
{
    public function __construct(
        private readonly EditFormEntityResolver $entityResolver,
        private readonly EditFormRenderer $renderer,
    ) {
    }

    public function handle(AjaxEditFormQueryDto $payload): EditFormViewResult
    {
        $context = $this->entityResolver->resolve($payload->entity, $payload->id);

        if (null === $context) {
            return EditFormViewResult::notFound();
        }

        $form = $this->renderer->build($context, $payload->columns);

        return EditFormViewResult::success($this->renderer->render($form));
    }
}
