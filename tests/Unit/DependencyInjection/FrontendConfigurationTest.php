<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection;

use Pentiminax\UX\DataTables\PentiminaxDataTablesBundle;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[CoversClass(PentiminaxDataTablesBundle::class)]
final class FrontendConfigurationTest extends TestCase
{
    #[Test]
    public function it_deprecates_an_unset_frontend_and_keeps_the_legacy_controller(): void
    {
        $deprecations = [];
        $builder      = $this->collect(static fn (): ContainerBuilder => new ContainerBuilder(), $deprecations);

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('becomes "core" in 2.0', $deprecations[0]);
        $this->assertStringContainsString('ux-datatables:install', $deprecations[0]);
        $this->assertFalse($builder->hasParameter('datatables.frontend'), 'An unset frontend must not be checked against controllers.json.');
        $this->assertSame(DataTablesExtension::CONTROLLER_NAME, $builder->getParameter('datatables.controller_name'));
    }

    #[Test]
    #[DataProvider('explicitFrontends')]
    public function it_accepts_an_explicit_frontend_without_deprecation(string $frontend, string $controllerName): void
    {
        $deprecations = [];
        $builder      = $this->collect(static fn (): ContainerBuilder => new ContainerBuilder(), $deprecations, ['frontend' => $frontend]);

        $this->assertSame([], $deprecations);
        $this->assertSame($frontend, $builder->getParameter('datatables.frontend'));
        $this->assertSame($controllerName, $builder->getParameter('datatables.controller_name'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function explicitFrontends(): iterable
    {
        yield 'legacy' => ['legacy', DataTablesExtension::CONTROLLER_NAME];
        yield 'core' => ['core', DataTablesExtension::CORE_CONTROLLER_NAME];
    }

    /**
     * @param list<string>         $deprecations
     * @param array<string, mixed> $config
     */
    private function collect(callable $newBuilder, array &$deprecations, array $config = []): ContainerBuilder
    {
        $bundle  = new PentiminaxDataTablesBundle();
        $builder = $newBuilder();
        $builder->setParameter('kernel.debug', false);
        $builder->setParameter('kernel.bundles', []);
        $builder->setParameter('kernel.bundles_metadata', []);

        $tree      = $bundle->getContainerExtension()->getConfiguration([], $builder)->getConfigTreeBuilder()->buildTree();
        $processed = (new Processor())->process($tree, [$config]);

        $instanceof   = [];
        $configurator = new ContainerConfigurator($builder, new PhpFileLoader($builder, new FileLocator()), $instanceof, \dirname(__DIR__, 3).'/src/PentiminaxDataTablesBundle.php', 'PentiminaxDataTablesBundle.php');

        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $bundle->loadExtension($processed, $configurator, $builder);
        } finally {
            restore_error_handler();
        }

        return $builder;
    }
}
