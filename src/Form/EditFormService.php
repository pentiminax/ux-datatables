<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;

final class EditFormService
{
    public function __construct(
        private readonly EditFormEntityResolver $resolver,
        private readonly EditFormBuilder $builder,
        private readonly EditModalRenderer $renderer,
        private readonly EditModalTemplateResolverInterface $templateResolver,
        private readonly ?MercureUpdatePublisher $mercurePublisher = null,
    ) {
    }

    public function handleView(AjaxEditFormQueryDto $payload): EditFormResult
    {
        if (null === $payload->dataTableClass) {
            return EditFormResult::badRequest('Edit modal requires a DataTable class (AbstractDataTable).');
        }

        $context = $this->resolver->resolve($payload->entity, $payload->id);

        if (null === $context) {
            return EditFormResult::notFound();
        }

        $columns = $this->templateResolver->resolveColumns($payload->dataTableClass);

        $form = $this->builder->buildForm(
            entity: $context->entity,
            columns: $columns,
            identifierFields: $context->identifierFields,
        );

        return EditFormResult::success($this->renderer->render($this->createRenderRequest(
            entity: $context->entity,
            form: $form,
            dataTableClass: $payload->dataTableClass,
        )));
    }

    public function handleSubmit(AjaxEditFormRequestDto $payload): EditFormResult
    {
        if (null === $payload->dataTableClass) {
            return EditFormResult::badRequest('Edit modal requires a DataTable class (AbstractDataTable).');
        }

        $context = $this->resolver->resolve($payload->entity, $payload->id);

        if (null === $context) {
            return EditFormResult::notFound();
        }

        $columns = $this->templateResolver->resolveColumns($payload->dataTableClass);

        $form = $this->builder->buildForm(
            entity: $context->entity,
            columns: $columns,
            identifierFields: $context->identifierFields,
        );

        $form->submit($payload->formData);

        if (!$form->isValid()) {
            return EditFormResult::invalid($this->renderer->renderBody($this->createRenderRequest(
                entity: $context->entity,
                form: $form,
                dataTableClass: $payload->dataTableClass,
            )));
        }

        $context->manager->flush();

        if (null !== $this->mercurePublisher && [] !== $payload->topics) {
            $this->mercurePublisher->publish($payload->topics, [
                'type' => 'edit',
                'id'   => $payload->id,
            ]);
        }

        return EditFormResult::success();
    }

    private function createRenderRequest(
        object $entity,
        \Symfony\Component\Form\FormInterface $form,
        ?string $dataTableClass,
    ): EditModalRenderRequest {
        return new EditModalRenderRequest(
            form: $form,
            entity: $entity,
            templatePath: $this->templateResolver->resolveChromeTemplate($dataTableClass),
            bodyTemplatePath: $this->templateResolver->resolveBodyTemplate(),
        );
    }
}
