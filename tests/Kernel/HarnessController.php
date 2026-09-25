<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessClientSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class HarnessController
{
    public function __construct(
        private Environment $twig,
        private HarnessClientSideDataTable $clientSideTable,
        private HarnessServerSideDataTable $serverSideTable,
    ) {
    }

    public function books(): Response
    {
        return new Response($this->twig->render('harness/books.html.twig', [
            'datatable' => $this->clientSideTable,
        ]));
    }

    public function twoTables(): Response
    {
        return new Response($this->twig->render('harness/two_tables.html.twig', [
            'clientSide' => $this->clientSideTable,
            'serverSide' => $this->serverSideTable,
        ]));
    }
}
