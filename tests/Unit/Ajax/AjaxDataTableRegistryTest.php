<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(AjaxDataTableRegistry::class)]
#[CoversClass(AjaxDataTableTokenManager::class)]
final class AjaxDataTableRegistryTest extends TestCase
{
    #[Test]
    public function it_resolves_registered_tables_by_token(): void
    {
        $table = $this->createMock(AbstractDataTable::class);

        $registry = $this->createRegistry(['custom.service_id' => $table], [
            'App\\DataTable\\UserDataTable' => 'custom.service_id',
        ]);

        $token = $registry->getToken('App\\DataTable\\UserDataTable');

        $this->assertIsString($token);
        $this->assertSame($table, $registry->get($token));
    }

    #[Test]
    public function it_returns_null_for_unknown_tables_and_tokens(): void
    {
        $registry = $this->createRegistry([], []);

        $this->assertNull($registry->getToken('App\\DataTable\\UnknownDataTable'));
        $this->assertNull($registry->get('unknown-token'));
    }

    private function createRegistry(array $services, array $serviceIdsByClass): AjaxDataTableRegistry
    {
        return new AjaxDataTableRegistry(
            new class($services) implements ContainerInterface {
                public function __construct(private readonly array $services)
                {
                }

                public function get(string $id): mixed
                {
                    return $this->services[$id];
                }

                public function has(string $id): bool
                {
                    return isset($this->services[$id]);
                }
            },
            new AjaxDataTableTokenManager('test-secret'),
            $serviceIdsByClass,
        );
    }
}
