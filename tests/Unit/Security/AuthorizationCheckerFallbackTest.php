<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Security;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AuthorizationCheckerFallbackTest extends TestCase
{
    use CollectsDeprecations;

    #[Test]
    public function a_hand_built_service_deprecates_the_allow_all_fallback(): void
    {
        $deprecations = $this->deprecationsOf(static fn () => new ColumnResolver());

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString(ColumnResolver::class, $deprecations[0]);
        $this->assertStringContainsString('a checker is required in 2.0', $deprecations[0]);
    }

    #[Test]
    public function a_runtime_factory_deprecates_the_fallback_once_it_resolves_columns(): void
    {
        $factory = new DataTableRuntimeFactory();

        $deprecations = $this->deprecationsOf(static fn () => (new \ReflectionMethod($factory, 'columnResolver'))->invoke($factory));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString(DataTableRuntimeFactory::class, $deprecations[0]);
    }

    #[Test]
    public function a_service_given_a_checker_stays_quiet(): void
    {
        $deprecations = $this->deprecationsOf(static fn () => new ColumnResolver(permissionChecker: new AuthorizationChecker()));

        $this->assertSame([], $deprecations);
    }

    #[Test]
    public function the_container_wires_a_checker_into_every_service_so_nothing_is_deprecated(): void
    {
        $kernel = new TwigAppKernel('test', true);

        $deprecations = $this->deprecationsOf(static function () use ($kernel): void {
            $kernel->boot();
            $container = $kernel->getContainer()->get('test.service_container');
            $container->get('test.datatables.twig_extension');
            $container->get('test.datatables.infrastructure');
        });
        $kernel->shutdown();

        $this->assertSame([], array_values(array_filter($deprecations, static fn (string $m): bool => str_contains($m, 'authorization checker'))));
    }
}
