<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableView;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessUnscopedBulkDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class UnscopedMutationsTest extends WebTestCase
{
    public function test_a_bulk_request_on_a_provider_that_cannot_check_the_row_scope_answers_403(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/actions');

        $csrf  = DataTableView::fromCrawler($crawler)->option('csrfToken');
        $token = static::getContainer()->get('test.datatables.ajax_registry')->getActionToken(HarnessUnscopedBulkDataTable::class);

        $client->request(
            'POST',
            '/datatables/ajax/bulk',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf],
            content: json_encode(['dataTable' => $token, 'action' => 'touch', 'ids' => [1], 'query' => ['draw' => '1']], \JSON_THROW_ON_ERROR),
        );

        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function test_a_toggle_on_a_table_without_an_entity_class_answers_as_a_missing_row(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/actions');

        $csrf  = DataTableView::fromCrawler($crawler)->option('csrfToken');
        $token = static::getContainer()->get('test.datatables.ajax_registry')->getActionToken(HarnessUnscopedBulkDataTable::class);

        $client->request(
            'PATCH',
            '/datatables/ajax/edit',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf],
            content: json_encode(['dataTable' => $token, 'id' => 1, 'field' => 'active', 'newValue' => true], \JSON_THROW_ON_ERROR),
        );

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
