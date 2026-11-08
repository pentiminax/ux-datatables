<?php

declare(strict_types=1);

use Pentiminax\UX\DataTables\Command\InstallCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('datatables.command.install', InstallCommand::class)
        ->arg('$projectDir', param('kernel.project_dir'))
        ->arg('$tables', tagged_locator('datatables.data_table'))
        ->tag('console.command')
        ->private();
};
