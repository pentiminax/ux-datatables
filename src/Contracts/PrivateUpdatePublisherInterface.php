<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * Publishes a table's mutation events as private Mercure updates, delivered only to subscribers
 * whose JWT authorizes the topic.
 *
 * A separate contract from {@see MercurePublisherInterface} so existing custom publishers keep
 * working. A table whose Mercure config uses credentials publishes through this interface when
 * the publisher implements it, and falls back to `publish()` otherwise.
 */
interface PrivateUpdatePublisherInterface
{
    /**
     * @param string|string[]      $topics
     * @param array<string, mixed> $data
     */
    public function publishPrivate(string|array $topics, array $data = []): string;
}
