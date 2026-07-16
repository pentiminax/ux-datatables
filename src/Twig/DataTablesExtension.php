<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Twig;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
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
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_datatable', $this->renderDataTable(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderDataTable(AbstractDataTable|DataTable $table, array $attributes = []): string
    {
        $dataTableClass = $table instanceof AbstractDataTable ? $table::class : null;

        if ($table instanceof AbstractDataTable) {
            $table = $table->getDataTable();
        }

        $table->columns($this->columnResolver->filterStaticPermissions($table->getColumns()));

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

        $view = array_merge($options, $table->getExtensions(), [
            'dataTableClass' => $dataTableClass,
            'editModal'      => [
                'adapter' => $table->getEditModalAdapter(),
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
