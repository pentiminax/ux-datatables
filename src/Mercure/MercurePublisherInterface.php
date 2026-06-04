<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

interface MercurePublisherInterface
{
    /**
     * @param string|string[]      $topics
     * @param array<string, mixed> $data
     */
    public function publish(string|array $topics, array $data = []): string;
}
