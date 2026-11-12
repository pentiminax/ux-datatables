<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection;

use Pentiminax\UX\DataTables\PentiminaxDataTablesBundle;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
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
final class PackageLoadingConfigurationTest extends TestCase
{
    #[Test]
    public function it_deprecates_an_unset_package_loading_and_keeps_the_builtin_loaders(): void
    {
        $deprecations = [];
        $builder      = $this->collect(static fn (): ContainerBuilder => new ContainerBuilder(), $deprecations);

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('becomes "registered" in 2.0', $deprecations[0]);
        $this->assertStringContainsString('ux-datatables:install', $deprecations[0]);
        $this->assertFalse($builder->hasParameter('datatables.package_loading'), 'An unset package_loading must not be checked against controllers.json.');
        $this->assertSame(DataTablesExtension::CONTROLLER_NAME, $builder->getParameter('datatables.controller_name'));
    }

    #[Test]
    public function it_deprecates_an_explicit_builtin_package_loading_and_keeps_the_builtin_loaders(): void
    {
        $deprecations = [];
        $builder      = $this->collect(static fn (): ContainerBuilder => new ContainerBuilder(), $deprecations, ['package_loading' => 'builtin']);

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"builtin" is deprecated', $deprecations[0]);
        $this->assertStringContainsString('ux-datatables:install', $deprecations[0]);
        $this->assertSame('builtin', $builder->getParameter('datatables.package_loading'));
        $this->assertSame(DataTablesExtension::CONTROLLER_NAME, $builder->getParameter('datatables.controller_name'));
    }

    #[Test]
    public function it_accepts_registered_package_loading_without_deprecation(): void
    {
        $deprecations = [];
        $builder      = $this->collect(static fn (): ContainerBuilder => new ContainerBuilder(), $deprecations, ['package_loading' => 'registered']);

        $this->assertSame([], $deprecations);
        $this->assertSame('registered', $builder->getParameter('datatables.package_loading'));
        $this->assertSame(DataTablesExtension::CORE_CONTROLLER_NAME, $builder->getParameter('datatables.controller_name'));
    }

    #[Test]
    public function it_stamps_a_declared_style_framework_into_the_default_options(): void
    {
        $deprecations = [];
        $builder      = $this->collect(static fn (): ContainerBuilder => new ContainerBuilder(), $deprecations, ['style_framework' => 'bs5', 'options' => ['stateSave' => true]]);

        $options = $builder->getParameter('datatables.options');

        $this->assertSame('bs5', $options['styleFramework']);
        $this->assertTrue($options['stateSave']);
    }

    #[Test]
    public function it_leaves_the_style_framework_unset_by_default(): void
    {
        $deprecations = [];
        $builder      = $this->collect(static fn (): ContainerBuilder => new ContainerBuilder(), $deprecations);

        $this->assertArrayNotHasKey('styleFramework', $builder->getParameter('datatables.options'));
    }

    #[Test]
    public function it_rejects_a_deprecated_style_framework(): void
    {
        $deprecations = [];
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->collect(static fn (): ContainerBuilder => new ContainerBuilder(), $deprecations, ['style_framework' => 'bs4']);
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
