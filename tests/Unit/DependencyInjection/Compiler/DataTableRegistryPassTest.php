<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection\Compiler;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\DataTableRegistryPass;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(DataTableRegistryPass::class)]
final class DataTableRegistryPassTest extends TestCase
{
    #[Test]
    public function it_registers_ajax_registry_keyed_by_data_table_class_and_backed_by_service_ids(): void
    {
        $container = new ContainerBuilder();

        $definition = (new Definition(FakeDataTableForRegistry::class))
            ->addTag(DataTableRegistryPass::TAG)
            ->setPublic(true);
        $container->setDefinition('app.custom_data_table_service', $definition);

        (new DataTableRegistryPass())->process($container);

        $this->assertTrue($container->hasDefinition(DataTableRegistryPass::REGISTRY_ID));

        $registryDefinition = $container->getDefinition(DataTableRegistryPass::REGISTRY_ID);
        $this->assertSame(AjaxDataTableRegistry::class, $registryDefinition->getClass());
        $this->assertSame([
            FakeDataTableForRegistry::class => 'app.custom_data_table_service',
        ], $registryDefinition->getArgument(2));

        $locatorDefinition = $container->getDefinition('datatables.ajax.registry.locator');
        $this->assertSame(ServiceLocator::class, $locatorDefinition->getClass());
        $this->assertArrayHasKey('app.custom_data_table_service', $locatorDefinition->getArgument(0));
    }

    #[Test]
    public function it_registers_empty_registry_when_no_data_tables_are_tagged(): void
    {
        $container = new ContainerBuilder();

        (new DataTableRegistryPass())->process($container);

        $registryDefinition = $container->getDefinition(DataTableRegistryPass::REGISTRY_ID);
        $this->assertSame([], $registryDefinition->getArgument(2));
    }
}

final class FakeDataTableForRegistry extends AbstractDataTable
{
}
