<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

final class NullMercurePublisher implements MercurePublisherInterface
{
    public function publish(string|array $topics, array $data = []): string
    {
        if ([] === $topics) {
            return '';
        }

        return '';
    }
}
