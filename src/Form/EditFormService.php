<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\MutationContext;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormInterface;

final class EditFormService
{
    private readonly PermissionChecker $permissionChecker;

    public function __construct(
        private readonly EntityLocator $locator,
        private readonly EditFormBuilder $builder,
        private readonly EditModalRenderer $renderer,
        private readonly EditModalTemplateResolverInterface $templateResolver,
        private readonly MercurePublisherInterface $publisher,
        private readonly ?MercureConfigResolverInterface $mercureConfigResolver = null,
        private readonly ?ContainerInterface $dataTables = null,
        ?PermissionChecker $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new PermissionChecker();
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

        if (null !== ($denied = $this->authorize($context->entity, $payload->entity, $payload->dataTableClass))) {
            return $denied;
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

        if (null !== ($denied = $this->authorize($context->entity, $payload->entity, $payload->dataTableClass))) {
            return $denied;
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

        $this->publisher->publish(MercureTopicResolver::resolve($this->mercureConfigResolver, $payload->entity, $this->dataTables, $payload->dataTableClass), [
            'type' => 'edit',
            'id'   => $payload->id,
        ]);

        return EditFormResult::success();
    }

    /**
     * Boolean toggles and deletes already require EDIT/DELETE plus a registered
     * DataTable purpose. The edit modal must not be a weaker write path: a client
     * can otherwise swap `entity`/`id`/`dataTableClass` and persist any mapped
     * column on any Doctrine entity they can name.
     *
     * An unresolvable DataTable fails closed: a custom EditModalTemplateResolver
     * able to resolve columns for an unregistered class must not turn the missing
     * table into a bypass of the table-purpose boundary.
     */
    private function authorize(object $entity, string $entityClass, string $dataTableClass): ?EditFormResult
    {
        if (!$this->permissionChecker->isGranted('EDIT', $entity)) {
            return EditFormResult::forbidden();
        }

        if (null === $this->dataTables || !$this->dataTables->has($dataTableClass)) {
            return EditFormResult::forbidden();
        }

        $dataTable = $this->dataTables->get($dataTableClass);
        if (!$dataTable instanceof AbstractDataTable) {
            return EditFormResult::forbidden();
        }

        $tableEntityClass = $dataTable->getEntityClass();
        if (null === $tableEntityClass || ltrim($tableEntityClass, '\\') !== ltrim($entityClass, '\\')) {
            return EditFormResult::forbidden();
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function identifierFields(MutationContext $context, string $entityClass): array
    {
        return $context->manager->getClassMetadata($entityClass)->getIdentifierFieldNames();
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
