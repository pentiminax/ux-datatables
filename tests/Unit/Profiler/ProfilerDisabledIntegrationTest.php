<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Profiler;

use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Tests\Kernel\ProfilerDisabledAppKernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ProfilerDisabledIntegrationTest extends TestCase
{
    /**
     * datatables.profiler and datatables.data_collector eagerly record on every request from
     * plain application code (AbstractDataTable::getResponse(), DataTablesExtension::
     * renderDataTable()), not just when Symfony's own profiler pulls a collect() -- so they
     * must not exist in the container at all when kernel.debug is off, not merely go unread.
     */
    #[Test]
    public function profiler_services_are_not_registered_when_debug_is_off(): void
    {
        $kernel = new ProfilerDisabledAppKernel('test', false);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $this->assertFalse(
            $container->has('datatables.profiler'),
            'datatables.profiler must not be registered when kernel.debug is false.',
        );
        $this->assertFalse(
            $container->has('datatables.data_collector'),
            'datatables.data_collector must not be registered when kernel.debug is false.',
        );
    }

    /**
     * datatables.infrastructure's profiler argument must resolve to null rather than fail
     * container compilation when datatables.profiler does not exist -- proving the
     * nullOnInvalid() wiring, not just the absence of the service on its own.
     */
    #[Test]
    public function infrastructure_resolves_a_null_profiler_when_debug_is_off(): void
    {
        $kernel = new ProfilerDisabledAppKernel('test', false);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var DataTableInfrastructure $infrastructure */
        $infrastructure = $container->get('test.datatables.infrastructure');

        $this->assertNull($infrastructure->profiler);
    }
}
