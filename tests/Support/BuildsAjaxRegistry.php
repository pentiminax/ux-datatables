<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Psr\Container\ContainerInterface;

/**
 * Builds an AjaxDataTableRegistry backed by a real token manager, so tests can
 * mint and resolve genuine table tokens without booting a container.
 *
 * The locator only knows the services the test explicitly hands over: any other
 * service id throws, which keeps token-only tests honest about never resolving.
 *
 * @internal
 */
trait BuildsAjaxRegistry
{
    /**
     * @param array<class-string, string> $serviceIdsByClass service id keyed by data table class
     * @param array<string, object>       $servicesById      services the locator may return
     */
    protected function createAjaxRegistry(array $serviceIdsByClass, array $servicesById = []): AjaxDataTableRegistry
    {
        return new AjaxDataTableRegistry(
            new class($servicesById) implements ContainerInterface {
                /**
                 * @param array<string, object> $services
                 */
                public function __construct(private readonly array $services)
                {
                }

                public function get(string $id): mixed
                {
                    return $this->services[$id] ?? throw new \LogicException(\sprintf('Service "%s" was not registered on the test registry.', $id));
                }

                public function has(string $id): bool
                {
                    return isset($this->services[$id]);
                }
            },
            new AjaxDataTableTokenManager('test-secret'),
            $serviceIdsByClass,
        );
    }
}
