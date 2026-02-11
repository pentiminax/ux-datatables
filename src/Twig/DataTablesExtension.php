<?php

namespace Pentiminax\UX\DataTables\Twig;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataTablesExtension extends AbstractExtension
{
    public function __construct(
        private StimulusHelper $stimulus,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_datatable', [$this, 'renderDataTable'], ['is_safe' => ['html']]),
        ];
    }

    public function renderDataTable(AbstractDataTable|DataTable $table, array $attributes = []): string
    {
        if ($table instanceof AbstractDataTable) {
            $table->prepareForRendering();
            $table = $table->getDataTable();
        }

        $table->setAttributes(array_merge($table->getAttributes(), $attributes));

        $controllers = [];

        if ($table->getDataController()) {
            $controllers[$table->getDataController()] = [];
        }

        $controllers['@pentiminax/ux-datatables/datatable'] = [
            'view' => array_merge($table->getOptions(), $table->getExtensions()),
        ];

        $stimulusAttributes = $this->stimulus->createStimulusAttributes();
        foreach ($controllers as $name => $controllerValues) {
            $stimulusAttributes->addController($name, $controllerValues);
        }

        foreach ($table->getAttributes() as $name => $value) {
            if ('data-controller' === $name) {
                continue;
            }

            if (true === $value) {
                $stimulusAttributes->addAttribute($name, $name);
            } elseif (false !== $value) {
                $stimulusAttributes->addAttribute($name, $value);
            }
        }

        return \sprintf('<table id="%s" %s></table>', $table->getId(), $stimulusAttributes);
    }
}
