<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\MutationContext;
use Symfony\Component\Form\FormInterface;

final class EditFormService
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly EditFormBuilder $builder,
        private readonly EditModalRenderer $renderer,
        private readonly EditModalTemplateResolverInterface $templateResolver,
        private readonly MercurePublisherInterface $publisher,
        private readonly ?MercureConfigResolverInterface $mercureConfigResolver = null,
    ) {
    }

    public function handleView(AjaxEditFormQueryDto $payload): EditFormResult
    {
        if (null === $payload->dataTableClass) {
            return EditFormResult::badRequest('Edit modal requires a DataTable class (AbstractDataTable).');
        }

        try {
            $context = $this->locator->locate($payload->entity, $payload->id);
        } catch (EntityNotFoundException) {
            return EditFormResult::notFound();
        }

        $columns = $this->templateResolver->resolveColumns($payload->dataTableClass);

        $form = $this->builder->buildForm(
            entity: $context->entity,
            columns: $columns,
            identifierFields: $this->identifierFields($context, $payload->entity),
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

        try {
            $context = $this->locator->locate($payload->entity, $payload->id);
        } catch (EntityNotFoundException) {
            return EditFormResult::notFound();
        }

        $columns = $this->templateResolver->resolveColumns($payload->dataTableClass);

        $form = $this->builder->buildForm(
            entity: $context->entity,
            columns: $columns,
            identifierFields: $this->identifierFields($context, $payload->entity),
        );

        $form->submit($payload->formData);

        if (!$form->isValid()) {
            $html = $this->renderer->renderBody(
                $this->createRenderRequest(
                    entity: $context->entity,
                    form: $form,
                    dataTableClass: $payload->dataTableClass,
                )
            );

            return EditFormResult::invalid($html);
        }

        $context->manager->flush();

        $this->publisher->publish($this->resolveTopics($payload->entity), [
            'type' => 'edit',
            'id'   => $payload->id,
        ]);

        return EditFormResult::success();
    }

    /**
     * @return string[]
     */
    private function identifierFields(MutationContext $context, string $entityClass): array
    {
        return $context->manager->getClassMetadata($entityClass)->getIdentifierFieldNames();
    }

    /**
     * Resolves the authoritative Mercure topics for the target entity server-side.
     *
     * Topics are never taken from the client request: they are derived from the
     * entity configuration through the same resolver used by the render path.
     *
     * @return string[]
     */
    private function resolveTopics(string $entityClass): array
    {
        return $this->mercureConfigResolver?->resolveMercureConfig($entityClass)?->topics ?? [];
    }

    private function createRenderRequest(
        object $entity,
        FormInterface $form,
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
