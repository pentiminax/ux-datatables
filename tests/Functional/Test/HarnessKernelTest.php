<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class HarnessKernelTest extends WebTestCase
{
    public function test_the_ajax_route_answers_for_a_registered_table(): void
    {
        $client   = static::createClient();
        $registry = static::getContainer()->get('datatables.ajax.registry');
        \assert($registry instanceof AjaxDataTableRegistry);

        $client->request('GET', '/datatables/ajax/data', [
            'table'  => $registry->getToken(HarnessServerSideDataTable::class),
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]);

        $this->assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true);

        $this->assertSame(3, $payload['recordsTotal']);
    }

    public function test_the_books_page_renders_a_client_side_table(): void
    {
        $client = static::createClient();
        $client->request('GET', '/books');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            'data-pentiminax--ux-datatables--datatable-view-value',
            (string) $client->getResponse()->getContent(),
        );
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
