<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Twig\Environment;

class EditModalRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $defaultTitle = 'Edit',
    ) {
    }

    public function render(EditModalRenderRequest $request): string
    {
        return $this->twig->render($request->templatePath, [
            'form'          => $request->form->createView(),
            'entity'        => $request->entity,
            'title'         => $this->defaultTitle,
            'body_template' => $request->bodyTemplatePath,
        ]);
    }

    public function renderBody(EditModalRenderRequest $request): string
    {
        return $this->twig->render($request->bodyTemplatePath, [
            'form'   => $request->form->createView(),
            'entity' => $request->entity,
        ]);
    }
}
