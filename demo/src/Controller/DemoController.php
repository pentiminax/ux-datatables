<?php

declare(strict_types=1);

namespace App\Controller;

use App\DataTable\BulkOrdersDataTable;
use App\DataTable\ClientSideDataTable;
use App\DataTable\ColumnsDataTable;
use App\DataTable\ColumnToolsDataTable;
use App\DataTable\CustomersDataTable;
use App\DataTable\ExportProductsDataTable;
use App\DataTable\FilteredOrdersDataTable;
use App\DataTable\LayoutOrdersDataTable;
use App\DataTable\OrdersDataTable;
use App\DataTable\ProductActionsDataTable;
use App\DataTable\ShowcaseProductsDataTable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', requirements: ['_locale' => 'en|fr'])]
final class DemoController extends AbstractController
{
    #[Route('/', name: 'overview')]
    public function overview(ShowcaseProductsDataTable $table): Response
    {
        return $this->render('demo/overview.html.twig', ['table' => $table]);
    }

    #[Route('/client-side', name: 'client_side')]
    public function clientSide(ClientSideDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/server-side', name: 'server_side')]
    public function serverSide(OrdersDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/columns', name: 'columns')]
    public function columns(ColumnsDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/filters', name: 'filters')]
    public function filters(FilteredOrdersDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/attributes', name: 'attributes')]
    public function attributes(CustomersDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/actions', name: 'actions')]
    public function actions(ProductActionsDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/bulk-actions', name: 'bulk_actions')]
    public function bulkActions(BulkOrdersDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/export', name: 'export')]
    public function export(ExportProductsDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/layout', name: 'layout')]
    public function layout(LayoutOrdersDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }

    #[Route('/column-tools', name: 'column_tools')]
    public function columnTools(ColumnToolsDataTable $table): Response
    {
        return $this->render('demo/page.html.twig', ['table' => $table]);
    }
}
