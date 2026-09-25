<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Symfony\Component\Form\FormInterface;

final readonly class EditModalRenderRequest
{
    public function __construct(
        public FormInterface $form,
        public object $entity,
        public string $templatePath,
        public string $bodyTemplatePath,
    ) {
    }
}
