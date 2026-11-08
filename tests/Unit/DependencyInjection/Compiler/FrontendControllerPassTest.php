<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection\Compiler;

use Pentiminax\UX\DataTables\DependencyInjection\Compiler\FrontendControllerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(FrontendControllerPass::class)]
final class FrontendControllerPassTest extends TestCase
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
    public function it_rejects_a_frontend_whose_controller_is_not_enabled(string $frontend, array $controllers, string $message): void
    {
        $this->writeControllers($controllers);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        $this->process($frontend);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function mismatches(): iterable
    {
        yield 'core with datatable_core disabled' => ['core', ['datatable' => ['enabled' => true], 'datatable_core' => ['enabled' => false]], '"datatable_core" controller is not enabled'];
        yield 'core with datatable_core missing' => ['core', ['datatable' => ['enabled' => true]], '"datatable_core" controller is not enabled'];
        yield 'legacy with datatable disabled' => ['legacy', ['datatable' => ['enabled' => false]], '"datatable" controller is disabled'];
    }

    /**
     * @param array<string, mixed> $controllers
     */
    #[Test]
    #[DataProvider('consistentControllers')]
    public function it_accepts_a_frontend_whose_controller_is_enabled(string $frontend, array $controllers): void
    {
        $this->writeControllers($controllers);

        $this->process($frontend);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function consistentControllers(): iterable
    {
        yield 'core with datatable_core enabled' => ['core', ['datatable_core' => ['enabled' => true], 'datatable' => ['enabled' => false]]];
        yield 'legacy with datatable enabled' => ['legacy', ['datatable' => ['enabled' => true]]];
        yield 'legacy with datatable unspecified' => ['legacy', []];
    }

    #[Test]
    public function it_skips_a_missing_file(): void
    {
        $this->process('core');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_skips_a_file_without_the_package(): void
    {
        file_put_contents($this->projectDir.'/assets/controllers.json', '{"controllers":{"other":{}}}');

        $this->process('core');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_skips_invalid_json(): void
    {
        file_put_contents($this->projectDir.'/assets/controllers.json', '{not json');

        $this->process('core');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_reads_nothing_without_debug(): void
    {
        $this->writeControllers(['datatable' => ['enabled' => false]]);

        $container = $this->container('legacy', debug: false);
        (new FrontendControllerPass())->process($container);

        $this->assertSame([], $container->getResources());
    }

    /**
     * @param array<string, mixed> $controllers
     */
    private function writeControllers(array $controllers): void
    {
        file_put_contents($this->projectDir.'/assets/controllers.json', json_encode(['controllers' => ['@pentiminax/ux-datatables' => $controllers]]));
    }

    private function process(string $frontend): void
    {
        (new FrontendControllerPass())->process($this->container($frontend, debug: true));
    }

    private function container(string $frontend, bool $debug): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->setParameter('kernel.project_dir', $this->projectDir);
        $container->setParameter('datatables.frontend', $frontend);

        return $container;
    }
}
