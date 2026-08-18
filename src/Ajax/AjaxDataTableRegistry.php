<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Exception\InvalidDataTableTokenException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Psr\Container\ContainerInterface;

final class AjaxDataTableRegistry
{
    /**
     * Action tokens are signed under their own prefix so that the read token — which
     * travels in a query string on /ajax/data, and therefore leaks through logs and
     * the Referer header — can never be replayed against an action route. Every route
     * accepting an action token reads it from the request body for the same reason.
     */
    private const string ACTION_TOKEN_PREFIX = 'action:';

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
        return $this->generateToken('', $dataTableClass);
    }

    public function getActionToken(string $dataTableClass): ?string
    {
        return $this->generateToken(self::ACTION_TOKEN_PREFIX, $dataTableClass);
    }

    public function get(string $token): ?AbstractDataTable
    {
        return $this->findByToken('', $token);
    }

    /**
     * @throws InvalidDataTableTokenException
     */
    public function resolveAction(string $token): ResolvedDataTable
    {
        $table = $this->findByToken(self::ACTION_TOKEN_PREFIX, $token);

        if (null === $table) {
            throw InvalidDataTableTokenException::invalidToken();
        }

        $entityClass = $table->getEntityClass();

        return new ResolvedDataTable(
            $table,
            null === $entityClass ? null : ltrim($entityClass, '\\'),
            $table::class,
        );
    }

    private function generateToken(string $prefix, string $dataTableClass): ?string
    {
        $dataTableClass = ltrim($dataTableClass, '\\');

        if (!isset($this->serviceIdsByClass[$dataTableClass])) {
            return null;
        }

        return $this->tokenManager->generateHmacSignature($prefix.$dataTableClass);
    }

    private function findByToken(string $prefix, string $token): ?AbstractDataTable
    {
        foreach ($this->serviceIdsByClass as $dataTableClass => $serviceId) {
            $generatedSignature = $this->tokenManager->generateHmacSignature($prefix.$dataTableClass);

            if (!hash_equals($generatedSignature, $token)) {
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
}
