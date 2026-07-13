<?php

declare(strict_types=1);

use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormController;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormSubmitController;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalTemplateResolver;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolverInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('datatables.form.column_to_form_type_mapper', ColumnToFormTypeMapper::class)
        ->private();

    $services->set('datatables.form.edit_form_builder', EditFormBuilder::class)
        ->arg(0, service('form.factory'))
        ->arg(1, service('datatables.form.column_to_form_type_mapper'))
        ->private();

    $services->set('datatables.form.edit_modal_renderer', EditModalRenderer::class)
        ->arg(0, service('twig'))
        ->arg(1, param('datatables.edit_modal.default_title'))
        ->private();

    $services->set('datatables.form.edit_modal_template_resolver', EditModalTemplateResolver::class)
        ->arg(0, tagged_locator('datatables.data_table'))
        ->arg(1, param('datatables.edit_modal.template'))
        ->arg(2, param('datatables.edit_modal.body_template'))
        ->private();

    $services->alias(EditModalTemplateResolverInterface::class, 'datatables.form.edit_modal_template_resolver')
        ->private();

    $services->set('datatables.form.edit_form_service', EditFormService::class)
        ->arg(0, service('datatables.mutation.locator'))
        ->arg(1, service('datatables.form.edit_form_builder'))
        ->arg(2, service('datatables.form.edit_modal_renderer'))
        ->arg(3, service('datatables.form.edit_modal_template_resolver'))
        ->arg(4, service(MercurePublisherInterface::class))
        ->arg(5, service(MercureTopicResolverInterface::class)->nullOnInvalid())
        ->private();

    $services->set('datatables.controller.ajax_edit_form', AjaxEditFormController::class)
        ->arg(0, service('datatables.form.edit_form_service'))
        ->tag('controller.service_arguments')
        ->public();

    $services->set('datatables.controller.ajax_edit_form_submit', AjaxEditFormSubmitController::class)
        ->arg(0, service('datatables.form.edit_form_service'))
        ->tag('controller.service_arguments')
        ->public();
};
