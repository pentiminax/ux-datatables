<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Model\DataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * @internal
 */
#[CoversClass(MercureUpdatePublisher::class)]
final class MercureUpdatePublisherTest extends TestCase
{
    #[Test]
    public function it_publishes_an_update(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return 'datatables/MyTable'   === $update->getTopics()[0]
                    && '{"action":"updated"}' === $update->getData();
            }))
            ->willReturn('urn:uuid:1234');

        $publisher = new MercureUpdatePublisher($hub);
        $result    = $publisher->publish('datatables/MyTable', ['action' => 'updated']);

        $this->assertSame('urn:uuid:1234', $result);
    }

    #[Test]
    public function it_publishes_with_empty_data(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return '[]' === $update->getData();
            }))
            ->willReturn('urn:uuid:5678');

        $publisher = new MercureUpdatePublisher($hub);
        $publisher->publish('datatables/MyTable');
    }

    #[Test]
    public function it_publishes_for_datatable(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure(hubUrl: '/.well-known/mercure');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return '/datatables/product-data-tables/{id}' === $update->getTopics()[0];
            }))
            ->willReturn('urn:uuid:abcd');

        $publisher = new MercureUpdatePublisher($hub);
        $publisher->publishForDataTable($table, ['action' => 'updated']);
    }

    #[Test]
    public function it_publishes_all_datatable_topics(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure(hubUrl: '/.well-known/mercure', topics: ['/api/products/{id}', '/api/categories/{id}']);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return ['/api/products/{id}', '/api/categories/{id}'] === $update->getTopics();
            }))
            ->willReturn('urn:uuid:topics');

        $publisher = new MercureUpdatePublisher($hub);
        $publisher->publishForDataTable($table, ['action' => 'updated']);
    }

    #[Test]
    public function it_throws_when_datatable_has_no_mercure_config(): void
    {
        $hub       = $this->createMock(HubInterface::class);
        $publisher = new MercureUpdatePublisher($hub);
        $table     = new DataTable('NoMercureTable');

        $this->expectException(\LogicException::class);
        $publisher->publishForDataTable($table);
    }
}
