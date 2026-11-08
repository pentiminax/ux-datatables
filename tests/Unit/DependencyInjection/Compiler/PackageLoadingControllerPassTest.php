<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection\Compiler;

use Pentiminax\UX\DataTables\DependencyInjection\Compiler\PackageLoadingControllerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(PackageLoadingControllerPass::class)]
final class PackageLoadingControllerPassTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/ux_datatables_pass_'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/assets', recursive: true);
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir.'/assets/controllers.json');
        @rmdir($this->projectDir.'/assets');
        @rmdir($this->projectDir);
    }

    /**
     * @param array<string, mixed> $controllers
     */
    #[Test]
    #[DataProvider('mismatches')]
    public function it_rejects_a_package_loading_whose_controller_is_not_enabled(string $packageLoading, array $controllers, string $message): void
    {
        $this->writeControllers($controllers);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        $this->process($packageLoading);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function mismatches(): iterable
    {
        yield 'registered with datatable_core disabled' => ['registered', ['datatable' => ['enabled' => true], 'datatable_core' => ['enabled' => false]], '"datatable_core" controller is not enabled'];
        yield 'registered with datatable_core missing' => ['registered', ['datatable' => ['enabled' => true]], '"datatable_core" controller is not enabled'];
        yield 'builtin with datatable disabled' => ['builtin', ['datatable' => ['enabled' => false]], '"datatable" controller is disabled'];
    }

    /**
     * @param array<string, mixed> $controllers
     */
    #[Test]
    #[DataProvider('consistentControllers')]
    public function it_accepts_a_package_loading_whose_controller_is_enabled(string $packageLoading, array $controllers): void
    {
        $this->writeControllers($controllers);

        $this->process($packageLoading);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function consistentControllers(): iterable
    {
        yield 'registered with datatable_core enabled' => ['registered', ['datatable_core' => ['enabled' => true], 'datatable' => ['enabled' => false]]];
        yield 'builtin with datatable enabled' => ['builtin', ['datatable' => ['enabled' => true]]];
        yield 'builtin with datatable unspecified' => ['builtin', []];
    }

    #[Test]
    public function it_skips_an_unset_package_loading_so_1x_apps_can_register_the_controller_themselves(): void
    {
        $this->writeControllers(['datatable' => ['enabled' => false]]);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.project_dir', $this->projectDir);
        (new PackageLoadingControllerPass())->process($container);

        $this->assertSame([], $container->getResources());
    }

    #[Test]
    public function it_skips_a_missing_file(): void
    {
        $this->process('registered');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_skips_a_file_without_the_package(): void
    {
        file_put_contents($this->projectDir.'/assets/controllers.json', '{"controllers":{"other":{}}}');

        $this->process('registered');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_skips_invalid_json(): void
    {
        file_put_contents($this->projectDir.'/assets/controllers.json', '{not json');

        $this->process('registered');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_reads_nothing_without_debug(): void
    {
        $this->writeControllers(['datatable' => ['enabled' => false]]);

        $container = $this->container('builtin', debug: false);
        (new PackageLoadingControllerPass())->process($container);

        $this->assertSame([], $container->getResources());
    }

    /**
     * @param array<string, mixed> $controllers
     */
    private function writeControllers(array $controllers): void
    {
        file_put_contents($this->projectDir.'/assets/controllers.json', json_encode(['controllers' => ['@pentiminax/ux-datatables' => $controllers]]));
    }

    private function process(string $packageLoading): void
    {
        (new PackageLoadingControllerPass())->process($this->container($packageLoading, debug: true));
    }

    private function container(string $packageLoading, bool $debug): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->setParameter('kernel.project_dir', $this->projectDir);
        $container->setParameter('datatables.package_loading', $packageLoading);

        return $container;
    }
}
