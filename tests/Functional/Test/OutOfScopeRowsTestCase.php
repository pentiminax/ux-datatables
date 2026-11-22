<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableView;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountAccount;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessScopedAccountsDataTable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Account 3 exists but the table scope hides it, so every row endpoint must answer for it as for a
 * missing row and leave it untouched, wherever the application mounted the routes.
 *
 * @internal
 */
abstract class OutOfScopeRowsTestCase extends WebTestCase
{
    abstract protected static function routePrefix(): string;

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function rowEndpoints(): iterable
    {
        yield 'delete' => ['DELETE', '/datatables/ajax/delete', []];
        yield 'toggle' => ['PATCH', '/datatables/ajax/edit', ['field' => 'active', 'newValue' => false]];
        yield 'detail' => ['POST', '/datatables/ajax/detail', []];
        yield 'edit form' => ['POST', '/datatables/ajax/edit-form/view', []];
        yield 'edit form submit' => ['POST', '/datatables/ajax/edit-form', ['formData' => ['name' => 'taken over']]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('rowEndpoints')]
    public function test_a_row_outside_the_scope_is_answered_as_a_missing_row_and_left_untouched(string $method, string $path, array $body): void
    {
        $client = $this->client();

        foreach ([3, 999] as $id) {
            $this->send($client, $method, $path, ['id' => $id] + $body);

            $this->assertSame(404, $client->getResponse()->getStatusCode(), $method.' '.$path.' on row '.$id);
        }

        $hidden = $this->account($client, 3);
        $this->assertSame('hidden', $hidden->name);
        $this->assertTrue($hidden->active);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('rowEndpoints')]
    public function test_a_row_inside_the_scope_is_not_refused(string $method, string $path, array $body): void
    {
        $client = $this->client();

        $this->send($client, $method, $path, ['id' => 1] + $body);

        $this->assertSame(200, $client->getResponse()->getStatusCode(), $method.' '.$path);
    }

    public function test_a_bulk_request_skips_the_row_outside_the_scope(): void
    {
        $client = $this->client();

        $this->send($client, 'POST', '/datatables/ajax/bulk', ['action' => 'deactivate', 'ids' => [1, 3], 'query' => ['draw' => '1']]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame(['success' => true, 'processed' => 1, 'skipped' => 1], json_decode((string) $client->getResponse()->getContent(), true));
        $this->assertFalse($this->account($client, 1)->active);
        $this->assertTrue($this->account($client, 3)->active);
    }

    private function client(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function send(KernelBrowser $client, string $method, string $path, array $body): void
    {
        $csrf  = DataTableView::fromCrawler($client->request('GET', '/actions'))->option('csrfToken');
        $token = static::getContainer()->get('test.datatables.ajax_registry')->getActionToken(HarnessScopedAccountsDataTable::class);

        $client->request(
            $method,
            static::routePrefix().$path,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf],
            content: json_encode(['dataTable' => $token] + $body, \JSON_THROW_ON_ERROR),
        );
    }

    private function account(KernelBrowser $client, int $id): CountAccount
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->find(CountAccount::class, $id);
    }
}
