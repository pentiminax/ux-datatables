<?php

declare(strict_types=1);

use Pentiminax\UX\DataTables\Maker\MakeDataTable;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('datatables.maker.datatable', MakeDataTable::class)
        ->arg('$propertyNameHumanizer', service('datatables.column.property_name_humanizer'))
        ->arg('$managerRegistry', service('doctrine')->nullOnInvalid())
        ->tag('maker.command')
        ->private();
};
