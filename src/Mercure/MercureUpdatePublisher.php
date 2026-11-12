<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Contracts\PrivateUpdatePublisherInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
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

    /**
     * Passing a DataTable is deprecated since 1.6, pass the AbstractDataTable instead. Removed in 2.0.
     */
    public function publishForDataTable(AbstractDataTable|DataTable $table, array $data = []): string
    {
        if ($table instanceof DataTable) {
            trigger_deprecation('pentiminax/ux-datatables', '1.6', 'Passing a "%s" to "%s()" is deprecated, pass the "%s" instead. Removed in 2.0.', DataTable::class, __METHOD__, AbstractDataTable::class);
        }

        $config = $table instanceof DataTable ? $table->getMercureConfig() : $table->resolveMercureConfigWithoutHydration();

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

        try {
            $update = new Update(
                topics: $topics,
                data: json_encode($data, \JSON_THROW_ON_ERROR),
                private: $private,
            );

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
