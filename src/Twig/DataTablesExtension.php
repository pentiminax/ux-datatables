<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataTablesExtension extends AbstractExtension
{
    /**
     * The Stimulus controller a rendered table is bound to, and the data attribute carrying its
     * serialized view. {@see \Pentiminax\UX\DataTables\Test\DataTableView} reads the attribute,
     * so both names are part of the bundle's public contract.
     */
    public const string CONTROLLER_NAME = '@pentiminax/ux-datatables/datatable';

    public const string VIEW_ATTRIBUTE = 'data-pentiminax--ux-datatables--datatable-view-value';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_datatable', [DataTablesRuntime::class, 'renderDataTable'], ['is_safe' => ['html']]),
        ];
    }
}
