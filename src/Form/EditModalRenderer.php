<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class EditModalRenderer
{
    public const string DEFAULT_TITLE = 'edit_modal.default_title';

    public function __construct(
        private readonly Environment $twig,
        private readonly string $defaultTitle = self::DEFAULT_TITLE,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function render(EditModalRenderRequest $request): string
    {
        return $this->twig->render($request->templatePath, [
            'form'          => $request->form->createView(),
            'entity'        => $request->entity,
            'title'         => $this->title(),
            'entity_label'  => $request->entity instanceof \Stringable ? (string) $request->entity : null,
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

    private function title(): string
    {
        if (self::DEFAULT_TITLE !== $this->defaultTitle || null === $this->translator) {
            return $this->defaultTitle;
        }

        return $this->translator->trans(self::DEFAULT_TITLE, [], 'DataTables');
    }
}
