<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection;

use Pentiminax\UX\DataTables\PentiminaxDataTablesBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
    public function it_needs_no_package_loading_option(): void
    {
        $deprecations = [];
        $config       = $this->process([], $deprecations);

        $this->assertSame([], $deprecations);
        $this->assertArrayNotHasKey('package_loading', $config);
    }

    #[Test]
    public function it_accepts_registered_package_loading_with_a_deprecation(): void
    {
        $deprecations = [];
        $this->process(['package_loading' => 'registered'], $deprecations);

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"data_tables.package_loading" option is deprecated and ignored', $deprecations[0]);
    }

    #[Test]
    public function it_rejects_builtin_package_loading_and_points_to_the_install_command(): void
    {
        $deprecations = [];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('ux-datatables:install');

        $this->process(['package_loading' => 'builtin'], $deprecations);
    }

    #[Test]
    public function it_leaves_the_style_framework_to_the_registered_loaders_by_default(): void
    {
        $deprecations = [];
        $builder      = $this->load([], $deprecations);

        $this->assertArrayNotHasKey('style_framework', $this->process([], $deprecations));
        $this->assertArrayNotHasKey('styleFramework', $builder->getParameter('datatables.options'));
    }

    #[Test]
    public function it_stamps_the_style_framework_into_the_default_options(): void
    {
        $deprecations = [];
        $builder      = $this->load(['style_framework' => 'bs5', 'options' => ['stateSave' => true]], $deprecations);

        $options = $builder->getParameter('datatables.options');

        $this->assertSame('bs5', $options['styleFramework']);
        $this->assertTrue($options['stateSave']);
    }

    #[Test]
    #[DataProvider('removedStyleFrameworks')]
    public function it_rejects_bootstrap_3_and_4_naming_the_supported_styles(string $value): void
    {
        $deprecations = [];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Use "bs5" for Bootstrap 5, or "dt"');

        $this->process(['style_framework' => $value], $deprecations);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function removedStyleFrameworks(): iterable
    {
        yield 'Bootstrap 3' => ['bs'];
        yield 'Bootstrap 4' => ['bs4'];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $deprecations
     *
     * @return array<string, mixed>
     */
    private function process(array $config, array &$deprecations): array
    {
        $builder = $this->newBuilder();
        $tree    = (new PentiminaxDataTablesBundle())->getContainerExtension()->getConfiguration([], $builder)->getConfigTreeBuilder()->buildTree();

        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            return (new Processor())->process($tree, [$config]);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $deprecations
     */
    private function load(array $config, array &$deprecations): ContainerBuilder
    {
        $builder      = $this->newBuilder();
        $processed    = $this->process($config, $deprecations);
        $instanceof   = [];
        $configurator = new ContainerConfigurator($builder, new PhpFileLoader($builder, new FileLocator()), $instanceof, \dirname(__DIR__, 3).'/src/PentiminaxDataTablesBundle.php', 'PentiminaxDataTablesBundle.php');

        (new PentiminaxDataTablesBundle())->loadExtension($processed, $configurator, $builder);

        return $builder;
    }

    private function newBuilder(): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.debug', false);
        $builder->setParameter('kernel.bundles', []);
        $builder->setParameter('kernel.bundles_metadata', []);

        return $builder;
    }
}
