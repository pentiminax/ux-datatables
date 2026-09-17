<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;

final class NullMercurePublisher implements MercurePublisherInterface
{
    public function publish(string|array $topics, array $data = []): string
    {
        return '';
    }
}
