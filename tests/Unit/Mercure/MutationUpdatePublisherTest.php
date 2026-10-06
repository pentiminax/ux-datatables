<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Mercure\MutationUpdatePublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * @internal
 */
#[CoversClass(MutationUpdatePublisher::class)]
#[CoversClass(MercureTopicResolver::class)]
final class MutationUpdatePublisherTest extends TestCase
{
    private const DATA = ['type' => 'delete', 'id' => '5'];

    #[Test]
    public function it_publishes_privately_when_the_table_subscribes_with_credentials(): void
    {
        $update = $this->publishThroughHub(withCredentials: true);

        $this->assertTrue($update->isPrivate());
        $this->assertSame(['/books/{id}'], $update->getTopics());
    }

    #[Test]
    public function it_publishes_publicly_when_the_table_subscribes_without_credentials(): void
    {
        $this->assertFalse($this->publishThroughHub(withCredentials: false)->isPrivate());
    }

    #[Test]
    public function it_falls_back_to_publish_and_warns_once_for_a_publisher_without_private_support(): void
    {
        $publisher = new class implements MercurePublisherInterface {
            public int $published = 0;

            public function publish(string|array $topics, array $data = []): string
            {
                ++$this->published;

                return '';
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $resolver = new MercureTopicResolver(
            $this->configResolverReturning(new MercureConfig(['/books/{id}'], withCredentials: true)),
            logger: $logger,
        );

        MutationUpdatePublisher::publish($publisher, $resolver, \stdClass::class, null, self::DATA);
        MutationUpdatePublisher::publish($publisher, $resolver, \stdClass::class, null, self::DATA);

        $this->assertSame(2, $publisher->published);
    }

    private function publishThroughHub(bool $withCredentials): Update
    {
        $published = null;
        $hub       = $this->createMock(HubInterface::class);
        $hub->method('publish')->willReturnCallback(static function (Update $update) use (&$published): string {
            $published = $update;

            return 'urn:uuid:1';
        });

        $resolver = new MercureTopicResolver(
            $this->configResolverReturning(new MercureConfig(['/books/{id}'], withCredentials: $withCredentials)),
        );

        MutationUpdatePublisher::publish(new MercureUpdatePublisher($hub), $resolver, \stdClass::class, null, self::DATA);

        $this->assertInstanceOf(Update::class, $published);

        return $published;
    }

    private function configResolverReturning(MercureConfig $config): MercureConfigResolver
    {
        $resolver = $this->createStub(MercureConfigResolver::class);
        $resolver->method('resolveMercureConfig')->willReturn($config);

        return $resolver;
    }
}
