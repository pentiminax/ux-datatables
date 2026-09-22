<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Controller\AjaxDataController;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Tests\Support\BuildsAjaxRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[CoversClass(AjaxDataController::class)]
final class AjaxDataControllerTest extends TestCase
{
    use BuildsAjaxRegistry;

    #[Test]
    public function it_throws_404_when_table_field_is_missing(): void
    {
        $controller = new AjaxDataController($this->createRegistry());
        $request    = new Request();

        $this->expectException(NotFoundHttpException::class);

        $controller($request);
    }

    #[Test]
    public function it_throws_404_when_table_token_is_unknown(): void
    {
        $controller = new AjaxDataController($this->createRegistry());
        $request    = new Request(query: ['table' => 'unknown-token']);

        $this->expectException(NotFoundHttpException::class);

        $controller($request);
    }

    #[Test]
    public function it_dispatches_to_resolved_data_table(): void
    {
        $expectedResponse = new JsonResponse(['draw' => 1, 'data' => []]);

        $dataTable = $this->createMock(AbstractDataTable::class);
        $dataTable->expects($this->once())->method('handleRequest');
        $dataTable->expects($this->once())->method('isRequestHandled')->willReturn(true);
        $dataTable->expects($this->once())->method('getResponse')->willReturn($expectedResponse);

        $registry = $this->createRegistry($dataTable);
        $token    = $registry->getToken('App\\UserDataTable');

        $controller = new AjaxDataController($registry);
        $response   = $controller(new Request(query: ['table' => $token, 'draw' => 1]));

        $this->assertSame($expectedResponse, $response);
    }

    #[Test]
    public function it_throws_400_when_request_is_not_a_datatables_payload(): void
    {
        $dataTable = $this->createMock(AbstractDataTable::class);
        $dataTable->expects($this->once())->method('handleRequest');
        $dataTable->expects($this->once())->method('isRequestHandled')->willReturn(false);
        $dataTable->expects($this->never())->method('getResponse');

        $registry = $this->createRegistry($dataTable);
        $token    = $registry->getToken('App\\UserDataTable');

        $controller = new AjaxDataController($registry);

        $this->expectException(BadRequestHttpException::class);

        $controller(new Request(query: ['table' => $token]));
    }

    #[Test]
    public function it_refuses_an_action_token_replayed_on_the_read_route(): void
    {
        $dataTable = $this->createMock(AbstractDataTable::class);
        $dataTable->expects($this->never())->method('handleRequest');

        $registry = $this->createRegistry($dataTable);

        $controller = new AjaxDataController($registry);

        $this->expectException(NotFoundHttpException::class);

        $controller(new Request(query: ['table' => $registry->getActionToken('App\\UserDataTable'), 'draw' => 1]));
    }

    private function createRegistry(?AbstractDataTable $table = null): AjaxDataTableRegistry
    {
        return $this->createAjaxRegistry(
            null === $table ? [] : ['App\\UserDataTable' => 'app.user_datatable'],
            null === $table ? [] : ['app.user_datatable' => $table],
        );
    }
}
