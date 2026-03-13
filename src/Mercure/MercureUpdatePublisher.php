<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Model\DataTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercureUpdatePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function publish(string|array $topics, array $data = []): string
    {
        $update = new Update(
            topics: $topics,
            data: json_encode($data)
        );

        try {
            return $this->hub->publish($update);
        } catch (\Throwable $exception) {
            $this->logPublishFailure($topics, $data, $exception);

            return '';
        }
    }

    public function publishForDataTable(DataTable $table, array $data = []): string
    {
        $config = $table->getMercureConfig();

        if (null === $config) {
            throw new \LogicException('The DataTable does not have Mercure configured.');
        }

        return $this->publish($config->topics, $data);
    }

    private function logPublishFailure(string|array $topics, array $data, \Throwable $exception): void
    {
        $context = [
            'topics' => $topics,
            'data' => $data,
            'exception' => $exception,
        ];

        $this->logger?->error('Failed to publish Mercure update.', $context);

    }
}
