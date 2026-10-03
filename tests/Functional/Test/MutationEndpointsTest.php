<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class MutationEndpointsTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function endpoints(): iterable
    {
        yield 'edit' => ['PATCH', '/datatables/ajax/edit'];
        yield 'edit form' => ['POST', '/datatables/ajax/edit-form/view'];
        yield 'edit submit' => ['POST', '/datatables/ajax/edit-form'];
        yield 'delete' => ['DELETE', '/datatables/ajax/delete'];
        yield 'detail' => ['POST', '/datatables/ajax/detail'];
        yield 'bulk' => ['POST', '/datatables/ajax/bulk'];
    }

    #[DataProvider('endpoints')]
    public function test_the_endpoint_maps_its_payload_with_the_serializer(string $method, string $path): void
    {
        $client = static::createClient();
        $client->request(
            $method,
            $path,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{}',
        );

        $this->assertStringNotContainsString(
            'Serializer component is not installed',
            (string) $client->getResponse()->getContent(),
        );
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 403, 404, 422]);
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
