<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\EventListener;

use Pentiminax\UX\DataTables\Exception\MutationException;
use Pentiminax\UX\DataTables\Http\JsonErrorResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

final class MutationExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof MutationException) {
            return;
        }

        $event->setResponse(JsonErrorResponse::create(
            $throwable->getClientMessage(),
            $throwable->getStatusCode(),
        ));
    }
}
