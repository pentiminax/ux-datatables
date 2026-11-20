<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Twig;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Renders a table for the `render_datatable()` Twig function.
 *
 * A Twig runtime, so that Twig only builds it, and the DataTableRuntime behind it (which renders
 * template columns through Twig), when a template calls the function.
 */
final class DataTablesRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly StimulusHelper $stimulus,
        private readonly AuthorizationChecker $permissionChecker,
        private readonly DataTableRuntime $runtime,
        private readonly ?DataTableProfiler $profiler = null,
    ) {
    }

    /**
     * @param array<array-key, mixed>|null $rows null renders the configured source, an empty array renders an empty inline table
     */
    public function renderDataTable(AbstractDataTable $table, array $attributes = [], ?array $rows = null): string
    {
        $definition = $this->runtime->definition($table);

        if (false === $this->permissionChecker->canAccessTable($table)) {
            throw new AccessDeniedException('Access to this DataTable is denied.');
        }

        $prepared = $this->runtime->render($table, rows: $rows);
        $view     = $prepared->getView();

        $attributes = array_merge($prepared->attributes, $attributes);
        $id         = \is_string($attributes['id'] ?? null) && '' !== $attributes['id'] ? $attributes['id'] : $prepared->id;
        unset($attributes['id']);

        $controllers = [];

        if (\is_string($attributes['data-controller'] ?? null) && '' !== $attributes['data-controller']) {
            $controllers[$attributes['data-controller']] = [];
        }

        $controllers[DataTablesExtension::CONTROLLER_NAME] = [
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

        $this->profiler?->collectRenderedTable($table::class, $prepared, $definition->table, [
            'id'           => $id,
            'entityClass'  => $definition->entityClass(),
            'payloadBytes' => \strlen($html),
        ]);

        return $html;
    }
}
