<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessActionsDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessClientSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessHydratedDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessUnresetDataTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class HarnessController
{
    public function __construct(
        private Environment $twig,
        private HarnessClientSideDataTable $clientSideTable,
        private HarnessServerSideDataTable $serverSideTable,
        private HarnessActionsDataTable $actionsTable,
        private HarnessHydratedDataTable $hydratedTable,
        private HarnessUnresetDataTable $unresetTable,
    ) {
    }

    public function books(): Response
    {
        return new Response($this->twig->render('harness/books.html.twig', [
            'datatable' => $this->clientSideTable,
        ]));
    }

    public function actions(): Response
    {
        return new Response($this->twig->render('harness/books.html.twig', [
            'datatable' => $this->actionsTable,
        ]));
    }

    public function twoTables(): Response
    {
        return new Response($this->twig->render('harness/two_tables.html.twig', [
            'clientSide' => $this->clientSideTable,
            'serverSide' => $this->serverSideTable,
        ]));
    }

    public function rows(Request $request): Response
    {
        return $this->renderRows($request, $this->clientSideTable, 'harness/rows.html.twig');
    }

    public function rowsPositional(Request $request): Response
    {
        return $this->renderRows($request, $this->clientSideTable, 'harness/rows_positional.html.twig');
    }

    public function hydratedRows(Request $request): Response
    {
        return $this->renderRows($request, $this->hydratedTable, 'harness/rows.html.twig');
    }

    public function unresetRows(Request $request): Response
    {
        return $this->renderRows($request, $this->unresetTable, 'harness/rows.html.twig');
    }

    private function renderRows(Request $request, object $table, string $template): Response
    {
        $rows = $request->query->has('rows')
            ? json_decode($request->query->getString('rows'), true, 512, \JSON_THROW_ON_ERROR)
            : null;

        return new Response($this->twig->render($template, ['datatable' => $table, 'rows' => $rows]));
    }
}
