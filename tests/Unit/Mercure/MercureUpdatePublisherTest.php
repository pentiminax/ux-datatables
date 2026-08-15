<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Model\DataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * @internal
 */
#[CoversClass(MercureUpdatePublisher::class)]
final class MercureUpdatePublisherTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('providePublishedUpdates')]
    public function it_publishes_an_update(array $data, string $expectedData): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) use ($expectedData) {
                return ['datatables/MyTable'] === $update->getTopics()
                    && $expectedData          === $update->getData();
            }))
            ->willReturn('urn:uuid:1234');

        $publisher = new MercureUpdatePublisher($hub);

        $this->assertSame('urn:uuid:1234', $publisher->publish('datatables/MyTable', $data));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providePublishedUpdates(): iterable
    {
        yield 'with data' => [['action' => 'updated'], '{"action":"updated"}'];
        yield 'with empty data' => [[], '[]'];
    }

    #[Test]
    public function it_does_not_publish_when_topics_is_an_empty_array(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('publish');

        $publisher = new MercureUpdatePublisher($hub);

        $this->assertSame('', $publisher->publish([], ['type' => 'edit', 'id' => 1]));
    }

    #[Test]
    public function it_logs_and_swallows_publish_failures(): void
    {
        $exception = new \RuntimeException('Mercure hub unavailable.');
        $hub       = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to publish Mercure update.',
                $this->callback(static function (array $context) use ($exception): bool {
                    return [
                        'topics'    => ['/topic/42'],
                        'data'      => ['type' => 'edit', 'id' => 42],
                        'exception' => $exception,
                    ] === $context;
                })
            );

        $publisher = new MercureUpdatePublisher($hub, $logger);

        $this->assertSame('', $publisher->publish(['/topic/42'], ['type' => 'edit', 'id' => 42]));
    }

    #[Test]
    public function it_publishes_for_datatable(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return ['/datatables/product-data-tables/{id}'] === $update->getTopics();
            }))
            ->willReturn('urn:uuid:abcd');

        $publisher = new MercureUpdatePublisher($hub);
        $publisher->publishForDataTable($table, ['action' => 'updated']);
    }

    #[Test]
    public function it_publishes_all_datatable_topics(): void
    {
        $table = (new DataTable('ProductDataTable'))
            ->mercure(topics: ['/api/products/{id}', '/api/categories/{id}']);

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
    public function it_throws_and_does_not_publish_when_datatable_has_no_mercure_config(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('publish');

        $publisher = new MercureUpdatePublisher($hub);
        $table     = new DataTable('NoMercureTable');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The DataTable does not have Mercure configured.');

        $publisher->publishForDataTable($table);
    }
}
