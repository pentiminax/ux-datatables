<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Symfony\Component\Form\FormInterface;
use Twig\Environment;

final class EditFormService
{
    public function __construct(
        private readonly EditFormEntityResolver $resolver,
        private readonly EditFormBuilder $builder,
        private readonly Environment $twig,
        private readonly ?MercureUpdatePublisher $mercurePublisher = null,
    ) {
    }

    public function handleView(AjaxEditFormQueryDto $payload): EditFormResult
    {
        $context = $this->resolver->resolve($payload->entity, $payload->id);

        if (null === $context) {
            return EditFormResult::notFound();
        }

        $form = $this->builder->buildForm(
            entity: $context->entity,
            columns: $payload->columns,
            identifierFields: $context->identifierFields,
        );

        return EditFormResult::success($this->renderForm($form));
    }

    public function handleSubmit(AjaxEditFormRequestDto $payload): EditFormResult
    {
        $context = $this->resolver->resolve($payload->entity, $payload->id);

        if (null === $context) {
            return EditFormResult::notFound();
        }

        $form = $this->builder->buildForm(
            entity: $context->entity,
            columns: $payload->columns,
            identifierFields: $context->identifierFields,
        );

        $form->submit($payload->formData);

        if (!$form->isValid()) {
            return EditFormResult::invalid($this->renderForm($form));
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

    private function renderForm(FormInterface $form): string
    {
        return $this->twig->render('@DataTables/form/edit_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
