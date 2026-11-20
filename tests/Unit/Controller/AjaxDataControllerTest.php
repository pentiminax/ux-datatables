<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Controller\AjaxDataController;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Pentiminax\UX\DataTables\Runtime\DataTableResponder;
use Pentiminax\UX\DataTables\Tests\Support\BuildsAjaxRegistry;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
    use BuildsRuntime;

    #[Test]
    public function it_throws_404_when_table_field_is_missing(): void
    {
        $controller = $this->controller($this->createRegistry());
        $request    = new Request();

        $this->expectException(NotFoundHttpException::class);

        $controller($request);
    }

    #[Test]
    public function it_throws_404_when_table_token_is_unknown(): void
    {
        $controller = $this->controller($this->createRegistry());
        $request    = new Request(query: ['table' => 'unknown-token']);

        $this->expectException(NotFoundHttpException::class);

        $controller($request);
    }

    #[Test]
    public function it_answers_a_data_tables_request_for_the_resolved_data_table(): void
    {
        $registry = $this->createRegistry(new DataControllerFixtureDataTable());
        $token    = $registry->getToken('App\\UserDataTable');

        $response = $this->controller($registry)(new Request(query: ['table' => $token, 'draw' => 1, 'start' => 0, 'length' => 10]));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(1, $payload['draw']);
        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertSame(['Alpha', 'Beta'], array_column($payload['data'], 'name'));
    }

    #[Test]
    public function it_throws_400_when_request_is_not_a_datatables_payload(): void
    {
        $registry = $this->createRegistry(new DataControllerFixtureDataTable());
        $token    = $registry->getToken('App\\UserDataTable');

        $this->expectException(BadRequestHttpException::class);

        $this->controller($registry)(new Request(query: ['table' => $token]));
    }

    #[Test]
    public function it_refuses_an_action_token_replayed_on_the_read_route(): void
    {
        $registry = $this->createRegistry(new DataControllerFixtureDataTable());

        $this->expectException(NotFoundHttpException::class);

        $this->controller($registry)(new Request(query: ['table' => $registry->getActionToken('App\\UserDataTable'), 'draw' => 1]));
    }

    private function controller(AjaxDataTableRegistry $registry): AjaxDataController
    {
        return new AjaxDataController($registry, new DataTableResponder(self::buildRuntime()));
    }

    private function createRegistry(?AbstractDataTable $table = null): AjaxDataTableRegistry
    {
        return $this->createAjaxRegistry(
            null === $table ? [] : ['App\\UserDataTable' => 'app.user_datatable'],
            null === $table ? [] : ['app.user_datatable' => $table],
        );
    }
}

final class DataControllerFixtureDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('name');
    }

    public function createDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([['name' => 'Alpha'], ['name' => 'Beta']], new DefaultRowMapper([]));
    }
}
