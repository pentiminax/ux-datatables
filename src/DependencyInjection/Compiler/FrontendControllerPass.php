<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DependencyInjection\Compiler;

use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails in debug when Twig would render a Stimulus controller that StimulusBundle does not register.
 */
final class FrontendControllerPass implements CompilerPassInterface
{
    private const PACKAGE = '@pentiminax/ux-datatables';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('kernel.debug') || !$container->hasParameter('datatables.frontend')) {
            return;
        }

        $file = $container->getParameter('kernel.project_dir').'/assets/controllers.json';

        if (!is_file($file)) {
            $container->addResource(new FileExistenceResource($file));

            return;
        }

        $container->addResource(new FileResource($file));

        $controllers = json_decode((string) file_get_contents($file), true)['controllers'][self::PACKAGE] ?? null;

        if (!\is_array($controllers)) {
            return;
        }

        $frontend = $container->getParameter('datatables.frontend');

        if ('core' === $frontend && true !== ($controllers['datatable_core']['enabled'] ?? false)) {
            throw new \LogicException('The "data_tables.frontend" option is "core" but the "datatable_core" controller is not enabled in assets/controllers.json. Enable "datatable_core" and disable "datatable" there, or set "frontend: legacy".');
        }

        if ('legacy' === $frontend && false === ($controllers['datatable']['enabled'] ?? true)) {
            throw new \LogicException('The "data_tables.frontend" option is "legacy" but the "datatable" controller is disabled in assets/controllers.json. Enable "datatable" there, or set "frontend: core" and enable "datatable_core".');
        }
    }
}
