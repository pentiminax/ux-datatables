<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * Publishes a table's mutation events to a Mercure hub so connected clients can refresh.
 *
 * Live updates are a side effect of a mutation that already succeeded, so an implementation must
 * never let a hub failure surface to the caller: it reports the failure (a log line) and returns.
 * Publishing must stay fire-and-forget for the same reason -- EntityMutator and EditFormService
 * call it after the entity is persisted.
 *
 * The bundle aliases this to MercureUpdatePublisher only when symfony/mercure is installed (see
 * config/mercure.php); {@see \Pentiminax\UX\DataTables\Mercure\NullMercurePublisher} is the
 * no-op used otherwise. Implement it to publish through another transport.
 */
interface MercurePublisherInterface
{
    /**
     * @param string|string[]      $topics
     * @param array<string, mixed> $data
     */
    public function publish(string|array $topics, array $data = []): string;
}
