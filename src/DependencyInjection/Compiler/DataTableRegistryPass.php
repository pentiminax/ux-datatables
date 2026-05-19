<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DependencyInjection\Compiler;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DataTableRegistryPass implements CompilerPassInterface
{
    public const REGISTRY_ID = 'datatables.ajax.registry';

    public const TAG = 'datatables.data_table';

    private const LOCATOR_ID = 'datatables.ajax.registry.locator';

    public function process(ContainerBuilder $container): void
    {
        $references        = [];
        $serviceIdsByClass = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class      = ltrim($definition->getClass() ?? $id, '\\');

            $references[$id]           = new Reference($id);
            $serviceIdsByClass[$class] = $id;
        }

        $locator = (new Definition(ServiceLocator::class))
            ->setArguments([$references])
            ->addTag('container.service_locator');

        $container->setDefinition(self::LOCATOR_ID, $locator);

        $container->setDefinition(self::REGISTRY_ID, (new Definition(AjaxDataTableRegistry::class))
            ->setArguments([
                new Reference(self::LOCATOR_ID),
                new Reference('datatables.ajax.token_manager'),
                $serviceIdsByClass,
            ]));
    }
}
