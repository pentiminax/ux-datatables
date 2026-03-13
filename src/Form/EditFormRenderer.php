<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Symfony\Component\Form\FormInterface;
use Twig\Environment;

final class EditFormRenderer
{
    public function __construct(
        private readonly EditFormBuilder $formBuilder,
        private readonly Environment $twig,
    ) {
    }

    public function build(EditFormEntityContext $context, array $columns): FormInterface
    {
        return $this->formBuilder->buildForm(
            entity: $context->entity,
            columns: $columns,
            identifierFields: $context->identifierFields,
        );
    }

    public function render(FormInterface $form): string
    {
        return $this->twig->render('@DataTables/form/edit_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
