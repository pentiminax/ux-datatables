<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Tests\Support\BuildsAjaxRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AjaxDataTableRegistry::class)]
#[CoversClass(AjaxDataTableTokenManager::class)]
final class AjaxDataTableRegistryTest extends TestCase
{
    use BuildsAjaxRegistry;

    private const TABLE_CLASS = 'App\\DataTable\\UserDataTable';

    private const SERVICE_ID = 'custom.service_id';

    #[Test]
    public function it_resolves_registered_tables_by_token(): void
    {
        $table    = $this->createMock(AbstractDataTable::class);
        $registry = $this->createAjaxRegistry(
            [self::TABLE_CLASS => self::SERVICE_ID],
            [self::SERVICE_ID => $table],
        );

        $token = $registry->getToken(self::TABLE_CLASS);

        $this->assertIsString($token);
        $this->assertSame($table, $registry->get($token));
    }

    #[Test]
    public function it_returns_null_for_unknown_tables_and_tokens(): void
    {
        $registry = $this->createAjaxRegistry([]);

        $this->assertNull($registry->getToken('App\\DataTable\\UnknownDataTable'));
        $this->assertNull($registry->getBooleanMutationToken('App\\DataTable\\UnknownDataTable'));
        $this->assertNull($registry->get('unknown-token'));
        $this->assertNull($registry->getForBooleanMutation('unknown-token'));
    }

    #[Test]
    public function it_uses_purpose_bound_tokens_for_boolean_mutations(): void
    {
        $table    = $this->createMock(AbstractDataTable::class);
        $registry = $this->createAjaxRegistry(
            [self::TABLE_CLASS => self::SERVICE_ID],
            [self::SERVICE_ID => $table],
        );

        $ajaxToken     = $registry->getToken(self::TABLE_CLASS);
        $mutationToken = $registry->getBooleanMutationToken(self::TABLE_CLASS);

        $this->assertIsString($ajaxToken);
        $this->assertIsString($mutationToken);
        $this->assertNotSame($ajaxToken, $mutationToken);
        $this->assertSame($table, $registry->getForBooleanMutation($mutationToken));
        $this->assertNull($registry->get($mutationToken));
        $this->assertNull($registry->getForBooleanMutation($ajaxToken));
    }

    #[Test]
    public function it_rejects_a_non_datatable_service_for_boolean_mutations(): void
    {
        $registry = $this->createAjaxRegistry(
            ['App\\DataTable\\InvalidDataTable' => 'invalid.service'],
            ['invalid.service' => new \stdClass()],
        );

        $token = $registry->getBooleanMutationToken('App\\DataTable\\InvalidDataTable');
        $this->assertNotNull($token);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Service "invalid.service" must be an instance of');

        $registry->getForBooleanMutation($token);
    }
}
