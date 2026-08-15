<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Boots the Twig test kernel for the whole test case and exposes its test container,
 * so wiring tests read services from the same booted application.
 *
 * @internal
 */
trait BootsTwigKernel
{
    private TwigAppKernel $kernel;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->kernel = new TwigAppKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer()->get('test.service_container');
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    private function service(string $id): object
    {
        $service = $this->kernel->getContainer()->get($id);

        $this->assertIsObject($service);

        return $service;
    }
}
