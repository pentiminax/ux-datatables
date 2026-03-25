<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Twig;

use Pentiminax\UX\DataTables\Column\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataTablesExtension extends AbstractExtension
{
    public function __construct(
        private StimulusHelper $stimulus,
        private TemplateColumnRenderer $templateColumnRenderer,
        private ActionRowDataResolver $actionRowDataResolver,
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
            $table = $table->getDataTable();
        }

        $table->setAttributes(array_merge($table->getAttributes(), $attributes));

        $controllers = [];

        if ($table->getDataController()) {
            $controllers[$table->getDataController()] = [];
        }

        $options = $table->getOptions();

        if (!empty($options['data'])) {
            $columns         = $table->getColumns();
            $renderTemplates = !$table->areTemplateColumnsRendered();
            $options['data'] = array_map(function (array $row) use ($columns, $renderTemplates): array {
                $resolvedRow = $renderTemplates
                    ? $this->templateColumnRenderer->renderRow($row, $row, $columns)
                    : $row;

                return $this->actionRowDataResolver->resolveRow($resolvedRow, $row, $columns);
            }, $options['data']);

            if ($renderTemplates) {
                $table->markTemplateColumnsRendered();
            }
        }

        $controllers['@pentiminax/ux-datatables/datatable'] = [
            'view' => array_merge($options, $table->getExtensions()),
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
