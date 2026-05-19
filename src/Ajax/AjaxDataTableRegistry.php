<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Psr\Container\ContainerInterface;

final class AjaxDataTableRegistry
{
    /**
     * @param array<class-string<AbstractDataTable>, string> $serviceIdsByClass
     */
    public function __construct(
        private readonly ContainerInterface $locator,
        private readonly AjaxDataTableTokenManager $tokenManager,
        private readonly array $serviceIdsByClass,
    ) {
    }

    public function getToken(string $dataTableClass): ?string
    {
        $dataTableClass = ltrim($dataTableClass, '\\');

        if (!isset($this->serviceIdsByClass[$dataTableClass])) {
            return null;
        }

        return $this->tokenManager->generateHmacSignature($dataTableClass);
    }

    public function get(string $token): ?AbstractDataTable
    {
        foreach ($this->serviceIdsByClass as $dataTableClass => $serviceId) {
            $generatedSignature = $this->tokenManager->generateHmacSignature($dataTableClass);
            if (!$this->validateSignature($generatedSignature, $token)) {
                continue;
            }

            $table = $this->locator->get($serviceId);

            if (!$table instanceof AbstractDataTable) {
                throw new \LogicException(\sprintf('Service "%s" must be an instance of "%s".', $serviceId, AbstractDataTable::class));
            }

            return $table;
        }

        return null;
    }

    private function validateSignature($generatedSignature, string $token): bool
    {
        return hash_equals($generatedSignature, $token);
    }
}
