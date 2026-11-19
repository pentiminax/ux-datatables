<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Ajax\AjaxActionResult;
use Pentiminax\UX\DataTables\Ajax\AuthorizedRow;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\MutationUpdatePublisher;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\MutationContext;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
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
        private readonly MutationFlusher $flusher,
        private readonly RowScopeGuard $scopeGuard,
        ?AuthorizationChecker $permissionChecker = null,
        private readonly ?ColumnResolver $columnResolver = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? AuthorizationChecker::fallbackFor(self::class);
    }

    /**
     * @internal
     */
    public function renderForm(AuthorizedRow $row): AjaxActionResult
    {
        return $this->renderContext($row->table, $row->context);
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $formData
     *
     * @throws MutationPersistenceException when the persistence layer rejects the flush
     */
    public function submitForm(AuthorizedRow $row, array $formData): AjaxActionResult
    {
        return $this->submitContext($row->table, $row->context, $row->id, $formData);
    }

    private function renderContext(ResolvedDataTable $dataTable, MutationContext $context): AjaxActionResult
    {
        return AjaxActionResult::success($this->renderer->render($this->createRenderRequest(
            entity: $context->entity,
            form: $this->buildForm($dataTable, $context),
            dataTableClass: $dataTable->dataTableClass,
        )));
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function submitContext(ResolvedDataTable $dataTable, MutationContext $context, int|string $id, array $formData): AjaxActionResult
    {
        $form = $this->buildForm($dataTable, $context);

        $form->submit($formData);

        if (!$form->isValid()) {
            return AjaxActionResult::invalid($this->renderer->renderBody(
                $this->createRenderRequest(
                    entity: $context->entity,
                    form: $form,
                    dataTableClass: $dataTable->dataTableClass,
                )
            ));
        }

        $this->flusher->flush($context->manager);

        MutationUpdatePublisher::publish($this->publisher, $this->topicResolver, $dataTable->requireEntityClass(), $dataTable->dataTableClass, [
            'type' => 'edit',
            'id'   => $id,
        ]);

        return AjaxActionResult::success();
    }

    private function buildForm(ResolvedDataTable $dataTable, MutationContext $context): FormInterface
    {
        $resolvedColumns = $this->templateResolver->resolveColumns($dataTable->dataTableClass);

        $columns = ($this->columnResolver ?? new ColumnResolver(permissionChecker: $this->permissionChecker))
            ->visibleColumns($resolvedColumns, $dataTable->dataTableClass)
            ->columns;

        $identifierFields = $context->manager
            ->getClassMetadata($dataTable->requireEntityClass())
            ->getIdentifierFieldNames();

        return $this->builder->buildForm(
            entity: $context->entity,
            columns: $columns,
            identifierFields: $identifierFields,
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
