<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NullMercurePublisher::class)]
final class NullMercurePublisherTest extends TestCase
{
    #[Test]
    public function it_publishes_nothing_without_failing(): void
    {
        $publisher = new NullMercurePublisher();

        $this->assertSame('', $publisher->publish('/topic/1', ['type' => 'edit', 'id' => 1]));
        $this->assertSame('', $publisher->publish([]));
    }
}
