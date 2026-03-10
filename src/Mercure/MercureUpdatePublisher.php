<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Model\DataTable;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercureUpdatePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    public function publish(string|array $topics, array $data = []): string
    {
        $update = new Update(
            topics: $topics,
            data: json_encode($data)
        );

        return $this->hub->publish($update);
    }

    public function publishForDataTable(DataTable $table, array $data = []): string
    {
        $config = $table->getMercureConfig();

        if (null === $config) {
            throw new \LogicException('The DataTable does not have Mercure configured.');
        }

        return $this->publish($config->topics, $data);
    }
}
