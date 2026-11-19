<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Twig;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataTablesExtension extends AbstractExtension
{
    /**
     * The Stimulus controller a rendered table is bound to, and the data attribute carrying its
     * serialized view. {@see \Pentiminax\UX\DataTables\Test\DataTableView} reads the attribute,
     * so both names are part of the bundle's public contract.
     */
    public const CONTROLLER_NAME = '@pentiminax/ux-datatables/datatable';

    public const VIEW_ATTRIBUTE = 'data-pentiminax--ux-datatables--datatable-view-value';

    public function __construct(
        private readonly StimulusHelper $stimulus,
        private readonly AuthorizationChecker $permissionChecker,
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
        $table->getConfiguredDataTable();

        if (false === $this->permissionChecker->canAccessTable($table)) {
            throw new AccessDeniedException('Access to this DataTable is denied.');
        }

        $prepared = $table->prepareRendering();
        $view     = $prepared->getView();

        $attributes = array_merge($prepared->attributes, $attributes);
        $id         = \is_string($attributes['id'] ?? null) && '' !== $attributes['id'] ? $attributes['id'] : $prepared->id;
        unset($attributes['id']);

        $controllers = [];

        if (\is_string($attributes['data-controller'] ?? null) && '' !== $attributes['data-controller']) {
            $controllers[$attributes['data-controller']] = [];
        }

        $controllers[self::CONTROLLER_NAME] = [
            'view' => $view,
        ];

        $stimulusAttributes = $this->stimulus->createStimulusAttributes();
        foreach ($controllers as $name => $controllerValues) {
            $stimulusAttributes->addController($name, $controllerValues);
        }

        foreach ($attributes as $name => $value) {
            if ('data-controller' === $name) {
                continue;
            }

            if (true === $value) {
                $stimulusAttributes->addAttribute($name, $name);
            } elseif (false !== $value) {
                $stimulusAttributes->addAttribute($name, $value);
            }
        }

        $html = \sprintf('<table id="%s" %s></table>', htmlspecialchars($id, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), $stimulusAttributes);

        $this->profiler?->collectRenderedTable($table::class, $prepared, $table->getConfiguredDataTable(), [
            'id'           => $id,
            'entityClass'  => $table->getEntityClass(),
            'payloadBytes' => \strlen($html),
        ]);

        return $html;
    }
}
