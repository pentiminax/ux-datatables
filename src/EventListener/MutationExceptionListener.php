<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\EventListener;

use Pentiminax\UX\DataTables\Exception\MutationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

final class MutationExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof MutationException) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'success' => false,
            'message' => $throwable->getClientMessage(),
        ], $throwable->getStatusCode()));
    }
}
