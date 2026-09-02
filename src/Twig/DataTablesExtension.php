<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Twig;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataTablesExtension extends AbstractExtension
{
    public function __construct(
        private readonly StimulusHelper $stimulus,
        private readonly TemplateColumnRenderer $templateColumnRenderer,
        private readonly ActionRowDataResolver $actionRowDataResolver,
        private readonly ColumnResolver $columnResolver,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private readonly ?AjaxDataTableRegistry $ajaxRegistry = null,
        private readonly ?DataTableProfiler $profiler = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_datatable', $this->renderDataTable(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderDataTable(AbstractDataTable $table, array $attributes = []): string
    {
        $dataTableClass = $table::class;
        $dataTable      = $table->getDataTable();

        $originalColumns = array_values($dataTable->getColumns());
        $columns         = $this->columnResolver->filterStaticPermissions($originalColumns);

        $dataTable->setAttributes(array_merge($dataTable->getAttributes(), $attributes));

        $controllers = [];

        if ($dataTable->getDataController()) {
            $controllers[$dataTable->getDataController()] = [];
        }

        $options            = $dataTable->getOptions();
        $options['columns'] = array_values(array_map(
            static fn (ColumnInterface $column): array => $column->jsonSerialize(),
            $columns,
        ));

        if (!empty($options['data'])) {
            $renderTemplates = !$dataTable->areTemplateColumnsRendered();
            $options['data'] = array_map(function (array $row) use ($columns, $originalColumns, $renderTemplates): array {
                $resolvedRow = $renderTemplates
                    ? $this->templateColumnRenderer->renderRow($row, $row, $columns)
                    : $row;
                $resolvedRow = $this->actionRowDataResolver->resolveRow($resolvedRow, $row, $columns);

                return $this->columnResolver->removeDeniedColumnValues($resolvedRow, $originalColumns);
            }, $options['data']);

            if ($renderTemplates) {
                $dataTable->markTemplateColumnsRendered();
            }
        }

        $view = array_merge($options, $dataTable->getExtensions(), [
            'dataTable' => $this->ajaxRegistry?->getActionToken($dataTableClass),
            'editModal' => [
                'adapter' => $dataTable->getEditModalAdapter(),
            ],
            'mutationsEnabled' => false,
        ]);

        if (null !== $locale = $this->requestStack?->getCurrentRequest()?->getLocale()) {
            $view['locale'] = $locale;
        }

        if (null !== $csrfToken = $this->getMutationToken()) {
            $view['csrfToken']        = $csrfToken;
            $view['mutationsEnabled'] = true;
        }

        $controllers['@pentiminax/ux-datatables/datatable'] = [
            'view' => $view,
        ];

        $stimulusAttributes = $this->stimulus->createStimulusAttributes();
        foreach ($controllers as $name => $controllerValues) {
            $stimulusAttributes->addController($name, $controllerValues);
        }

        foreach ($dataTable->getAttributes() as $name => $value) {
            if ('data-controller' === $name) {
                continue;
            }

            if (true === $value) {
                $stimulusAttributes->addAttribute($name, $name);
            } elseif (false !== $value) {
                $stimulusAttributes->addAttribute($name, $value);
            }
        }

        $this->profiler?->collectRenderedTable($dataTableClass, $dataTable, [
            'entityClass'     => $table->getEntityClass(),
            'originalColumns' => $originalColumns,
            'allowedColumns'  => $columns,
        ]);

        return \sprintf('<table id="%s" %s></table>', $dataTable->getId(), $stimulusAttributes);
    }

    private function getMutationToken(): ?string
    {
        if (null === $this->csrfTokenManager) {
            return null;
        }

        try {
            return $this->csrfTokenManager->getToken(MutationTokenValidator::TOKEN_ID)->getValue();
        } catch (SessionNotFoundException) {
            return null;
        }
    }
}
