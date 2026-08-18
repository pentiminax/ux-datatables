<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Exception\InvalidDataTableTokenException;
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
#[CoversClass(ResolvedDataTable::class)]
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
        $this->assertNull($registry->getActionToken('App\\DataTable\\UnknownDataTable'));
        $this->assertNull($registry->get('unknown-token'));

        $this->expectException(InvalidDataTableTokenException::class);
        $registry->resolveAction('unknown-token');
    }

    #[Test]
    public function it_uses_purpose_bound_tokens_for_actions(): void
    {
        $table = new RegistryDataTableFixture();

        $registry = $this->createAjaxRegistry(
            [self::TABLE_CLASS => self::SERVICE_ID],
            [self::SERVICE_ID => $table],
        );

        $ajaxToken   = $registry->getToken(self::TABLE_CLASS);
        $actionToken = $registry->getActionToken(self::TABLE_CLASS);

        $this->assertIsString($ajaxToken);
        $this->assertIsString($actionToken);
        $this->assertNotSame($ajaxToken, $actionToken);
        $this->assertNull($registry->get($actionToken));

        $resolved = $registry->resolveAction($actionToken);
        $this->assertSame($table, $resolved->table);
        $this->assertSame(RegistryEntityFixture::class, $resolved->entityClass);
        $this->assertSame($table::class, $resolved->dataTableClass);

        $this->expectException(InvalidDataTableTokenException::class);
        $registry->resolveAction($ajaxToken);
    }

    #[Test]
    public function it_rejects_a_table_without_an_entity_class(): void
    {
        $table = new EntitylessDataTableFixture();

        $registry = $this->createAjaxRegistry(
            [self::TABLE_CLASS => self::SERVICE_ID],
            [self::SERVICE_ID => $table],
        );

        $this->expectException(InvalidDataTableTokenException::class);
        $this->expectExceptionMessage('must define an entity class');

        $registry->resolveAction((string) $registry->getActionToken(self::TABLE_CLASS))->requireEntityClass();
    }

    #[Test]
    public function it_rejects_a_non_datatable_service_for_actions(): void
    {
        $registry = $this->createAjaxRegistry(
            ['App\\DataTable\\InvalidDataTable' => 'invalid.service'],
            ['invalid.service' => new \stdClass()],
        );

        $token = $registry->getActionToken('App\\DataTable\\InvalidDataTable');
        $this->assertNotNull($token);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Service "invalid.service" must be an instance of');

        $registry->resolveAction($token);
    }
}

final class RegistryEntityFixture
{
}

#[AsDataTable(entityClass: RegistryEntityFixture::class)]
final class RegistryDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}

final class EntitylessDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}
