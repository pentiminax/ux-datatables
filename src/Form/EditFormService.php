<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
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

    public function handleView(ResolvedDataTable $dataTable, int|string $id): EditFormResult
    {
        try {
            $context = $this->locator->locate($dataTable->requireEntityClass(), $id);
        } catch (EntityNotFoundException) {
            return EditFormResult::notFound();
        }

        if (!$this->permissionChecker->isGranted('EDIT', $context->entity)) {
            return EditFormResult::forbidden();
        }

        return EditFormResult::success($this->renderer->render($this->createRenderRequest(
            entity: $context->entity,
            form: $this->buildForm($dataTable, $context),
            dataTableClass: $dataTable->dataTableClass,
        )));
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function handleSubmit(ResolvedDataTable $dataTable, int|string $id, array $formData): EditFormResult
    {
        try {
            $context = $this->locator->locate($dataTable->requireEntityClass(), $id);
        } catch (EntityNotFoundException) {
            return EditFormResult::notFound();
        }

        if (!$this->permissionChecker->isGranted('EDIT', $context->entity)) {
            return EditFormResult::forbidden();
        }

        $form = $this->buildForm($dataTable, $context);

        $form->submit($formData);

        if (!$form->isValid()) {
            $html = $this->renderer->renderBody(
                $this->createRenderRequest(
                    entity: $context->entity,
                    form: $form,
                    dataTableClass: $dataTable->dataTableClass,
                )
            );

            return EditFormResult::invalid($html);
        }

        $context->manager->flush();

        $this->publisher->publish(MercureTopicResolver::resolve($this->mercureConfigResolver, $dataTable->requireEntityClass(), $this->dataTables, $dataTable->dataTableClass), [
            'type' => 'edit',
            'id'   => $id,
        ]);

        return EditFormResult::success();
    }

    private function buildForm(ResolvedDataTable $dataTable, MutationContext $context): FormInterface
    {
        return $this->builder->buildForm(
            entity: $context->entity,
            columns: (new ColumnResolver(permissionChecker: $this->permissionChecker))
                ->filterStaticPermissions($this->templateResolver->resolveColumns($dataTable->dataTableClass)),
            identifierFields: $context->manager->getClassMetadata($dataTable->requireEntityClass())->getIdentifierFieldNames(),
        );
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
