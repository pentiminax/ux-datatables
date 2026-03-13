<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;

final class EditFormSubmissionHandler
{
    public function __construct(
        private readonly EditFormEntityResolver $entityResolver,
        private readonly EditFormRenderer $renderer,
        private readonly ?MercureUpdatePublisher $mercurePublisher = null,
    ) {
    }

    public function handle(AjaxEditFormRequestDto $payload): EditFormSubmissionResult
    {
        $context = $this->entityResolver->resolve($payload->entity, $payload->id);

        if (null === $context) {
            return EditFormSubmissionResult::notFound();
        }

        $form = $this->renderer->build($context, $payload->columns);
        $form->submit($payload->formData);

        if (!$form->isValid()) {
            return EditFormSubmissionResult::invalid($this->renderer->render($form));
        }

        $context->manager->flush();

        if (null !== $this->mercurePublisher && [] !== $payload->topics) {
            $this->mercurePublisher->publish($payload->topics, [
                'type' => 'edit',
                'id'   => $payload->id,
            ]);
        }

        return EditFormSubmissionResult::success();
    }
}
