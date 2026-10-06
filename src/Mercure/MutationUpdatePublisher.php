<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Contracts\PrivateUpdatePublisherInterface;

/**
 * @internal
 */
final class MutationUpdatePublisher
{
    /**
     * @param array<string, mixed> $data
     */
    public static function publish(
        MercurePublisherInterface $publisher,
        MercureTopicResolver $topicResolver,
        string $entityClass,
        ?string $dataTableClass,
        array $data,
    ): void {
        $config = $topicResolver->resolveConfig($entityClass, $dataTableClass);
        $topics = $config?->topics ?? [];

        if (!$config?->withCredentials) {
            $publisher->publish($topics, $data);

            return;
        }

        if ($publisher instanceof PrivateUpdatePublisherInterface) {
            $publisher->publishPrivate($topics, $data);

            return;
        }

        $topicResolver->warnPublishedPublicly($publisher);
        $publisher->publish($topics, $data);
    }
}
