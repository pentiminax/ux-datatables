<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Ajax\AjaxActionResult;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\MutationContext;
use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Symfony\Component\Form\FormInterface;

final class EditFormService
{
    private readonly AuthorizationChecker $permissionChecker;

    public function __construct(
        private readonly EntityLocator $locator,
        private readonly EditFormBuilder $builder,
        private readonly EditModalRenderer $renderer,
        private readonly EditModalTemplateResolver $templateResolver,
        private readonly MercurePublisherInterface $publisher,
        private readonly MercureTopicResolver $topicResolver,
        ?AuthorizationChecker $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new AuthorizationChecker();
    }

    public function handleView(ResolvedDataTable $dataTable, int|string $id): AjaxActionResult
    {
        $action = $this->resolveEditAction($dataTable);
        if (null === $action) {
            return AjaxActionResult::forbidden();
        }

        try {
            $context = $this->locator->locate($dataTable->requireEntityClass(), $id);
        } catch (EntityNotFoundException) {
            return AjaxActionResult::notFound();
        }

        if (!$this->isGranted($dataTable, $action, $context->entity)) {
            return AjaxActionResult::forbidden();
        }

        return AjaxActionResult::success($this->renderer->render($this->createRenderRequest(
            entity: $context->entity,
            form: $this->buildForm($dataTable, $context),
            dataTableClass: $dataTable->dataTableClass,
        )));
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function handleSubmit(ResolvedDataTable $dataTable, int|string $id, array $formData): AjaxActionResult
    {
        $action = $this->resolveEditAction($dataTable);
        if (null === $action) {
            return AjaxActionResult::forbidden();
        }

        try {
            $context = $this->locator->locate($dataTable->requireEntityClass(), $id);
        } catch (EntityNotFoundException) {
            return AjaxActionResult::notFound();
        }

        if (!$this->isGranted($dataTable, $action, $context->entity)) {
            return AjaxActionResult::forbidden();
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

            return AjaxActionResult::invalid($html);
        }

        $context->manager->flush();

        $this->publisher->publish($this->topicResolver->resolve($dataTable->requireEntityClass(), $dataTable->dataTableClass), [
            'type' => 'edit',
            'id'   => $id,
        ]);

        return AjaxActionResult::success();
    }

    private function buildForm(ResolvedDataTable $dataTable, MutationContext $context): FormInterface
    {
        $columns = (new ColumnResolver(permissionChecker: $this->permissionChecker))
            ->filterStaticPermissions($this->templateResolver->resolveColumns($dataTable->dataTableClass), $dataTable->dataTableClass);

        $identifierFields = $context->manager
            ->getClassMetadata($dataTable->requireEntityClass())
            ->getIdentifierFieldNames();

        return $this->builder->buildForm(
            entity: $context->entity,
            columns: $columns,
            identifierFields: $identifierFields,
        );
    }

    private function resolveEditAction(ResolvedDataTable $dataTable): ?Action
    {
        $action = $dataTable->findAction(ActionType::Edit);

        if (null === $action) {
            return null;
        }

        return $this->permissionChecker->isGranted(Permission::DT_EXECUTE_ACTION, new ActionPermissionContext(
            $dataTable->dataTableClass,
            $action,
            null,
            false,
        )) ? $action : null;
    }

    private function isGranted(ResolvedDataTable $dataTable, Action $action, object $entity): bool
    {
        return $this->permissionChecker->isGranted(Permission::DT_EDIT_ROW, $entity)
            && $this->permissionChecker->isGranted(Permission::DT_EXECUTE_ACTION, new ActionPermissionContext(
                $dataTable->dataTableClass,
                $action,
                $entity,
                $action->hasPerRowPermission(),
            ));
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
