<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\EventListener\MutationExceptionListener;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Builds a real RowActionGate over the given registry and Doctrine, for controller tests.
 *
 * @internal
 */
trait BuildsRowActionGate
{
    /**
     * What the client receives for an exception thrown by a controller.
     */
    protected function clientResponse(\Throwable $throwable): Response
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $throwable);
        (new MutationExceptionListener())($event);

        return $event->getResponse() ?? throw new \LogicException('The exception was not mapped to a response.');
    }

    protected function rowActionGate(
        AjaxDataTableRegistry $registry,
        ManagerRegistry $doctrine,
        CsrfTokenManagerInterface $csrfTokenManager,
        ?RowScopeGuard $scopeGuard = null,
        ?AuthorizationChecker $permissionChecker = null,
    ): RowActionGate {
        $locator = new EntityLocator($doctrine);
        $permissionChecker ??= new AuthorizationChecker(new TestAuthorizationChecker());

        return new RowActionGate(
            new MutationTokenValidator($csrfTokenManager),
            $registry,
            new BooleanMutationContextResolver($registry, $permissionChecker),
            $scopeGuard ?? new RowScopeGuard($locator),
            $locator,
            $permissionChecker,
        );
    }
}
