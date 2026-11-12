<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableView;
use Pentiminax\UX\DataTables\Tests\Kernel\NoFormAppKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class NoFormRoutesTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function editFormPaths(): iterable
    {
        yield 'view' => ['/datatables/ajax/edit-form/view'];
        yield 'submit' => ['/datatables/ajax/edit-form'];
    }

    #[DataProvider('editFormPaths')]
    public function test_the_edit_form_endpoints_answer_not_found_without_the_form_component(string $path): void
    {
        $client = static::createClient();
        $client->request('POST', $path, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function test_the_other_endpoints_stay_available(): void
    {
        $client = static::createClient();
        $client->request('POST', '/datatables/ajax/detail', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        $this->assertSame(422, $client->getResponse()->getStatusCode());
    }

    public function test_a_table_with_row_actions_renders_without_the_edit_form_urls(): void
    {
        $client = static::createClient();
        $view   = DataTableView::fromCrawler($client->request('GET', '/actions'));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame([
            'delete' => '/datatables/ajax/delete',
            'toggle' => '/datatables/ajax/edit',
            'detail' => '/datatables/ajax/detail',
        ], $view->option('rowActionUrls'));
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new NoFormAppKernel('test', true);
    }
}
