<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Contracts\PrivateUpdatePublisherInterface;

final class NullMercurePublisher implements MercurePublisherInterface, PrivateUpdatePublisherInterface
{
    public function publish(string|array $topics, array $data = []): string
    {
        return '';
    }

    public function publishPrivate(string|array $topics, array $data = []): string
    {
        return '';
    }
}
