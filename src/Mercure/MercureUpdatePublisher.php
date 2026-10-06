<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Contracts\PrivateUpdatePublisherInterface;
use Pentiminax\UX\DataTables\Model\DataTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercureUpdatePublisher implements MercurePublisherInterface, PrivateUpdatePublisherInterface
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function publish(string|array $topics, array $data = []): string
    {
        return $this->send($topics, $data, false);
    }

    public function publishPrivate(string|array $topics, array $data = []): string
    {
        return $this->send($topics, $data, true);
    }

    public function publishForDataTable(DataTable $table, array $data = []): string
    {
        $config = $table->getMercureConfig();

        if (null === $config) {
            throw new \LogicException('The DataTable does not have Mercure configured.');
        }

        return $config->withCredentials
            ? $this->publishPrivate($config->topics, $data)
            : $this->publish($config->topics, $data);
    }

    private function send(string|array $topics, array $data, bool $private): string
    {
        if ([] === $topics) {
            return '';
        }

        $update = new Update(
            topics: $topics,
            data: json_encode($data),
            private: $private,
        );

        try {
            return $this->hub->publish($update);
        } catch (\Throwable $exception) {
            $this->logPublishFailure($topics, $data, $exception);

            return '';
        }
    }

    private function logPublishFailure(string|array $topics, array $data, \Throwable $exception): void
    {
        $context = [
            'topics'    => $topics,
            'data'      => $data,
            'exception' => $exception,
        ];

        $this->logger?->error('Failed to publish Mercure update.', $context);
    }
}
