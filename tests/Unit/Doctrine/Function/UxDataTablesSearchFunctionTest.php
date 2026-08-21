<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Doctrine\Function;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Query\AST\InputParameter;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\SqlWalker;
use Pentiminax\UX\DataTables\Doctrine\Function\UxDataTablesSearchFunction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UxDataTablesSearchFunction::class)]
final class UxDataTablesSearchFunctionTest extends TestCase
{
    #[Test]
    public function it_wraps_in_cast_and_lower_on_postgresql(): void
    {
        $sql = $this->invoke_get_sql(new PostgreSQLPlatform(), 'e0_.id', '?');

        $this->assertSame('CASE WHEN LOWER(CAST(e0_.id AS TEXT)) LIKE ? THEN 1 ELSE 0 END', $sql);
    }

    #[Test]
    #[DataProvider('non_postgresql_platforms')]
    public function it_applies_lower_without_cast_on_non_postgresql(object $platform, string $label): void
    {
        $sql = $this->invoke_get_sql($platform, 'e0_.name', '?');

        $this->assertSame('CASE WHEN LOWER(e0_.name) LIKE ? THEN 1 ELSE 0 END', $sql, $label);
    }

    /**
     * @return iterable<string, array{object, string}>
     */
    public static function non_postgresql_platforms(): iterable
    {
        yield 'mysql' => [new MySQLPlatform(), 'MySQL'];
        yield 'sqlite' => [new SQLitePlatform(), 'SQLite'];
    }

    #[Test]
    public function it_uses_the_dispatched_value_placeholder_from_the_input_parameter(): void
    {
        $sql = $this->invoke_get_sql(new PostgreSQLPlatform(), 'e0_.email', ':search_0');

        $this->assertSame('CASE WHEN LOWER(CAST(e0_.email AS TEXT)) LIKE :search_0 THEN 1 ELSE 0 END', $sql);
    }

    #[Test]
    public function it_also_applies_cast_for_postgresql_subclasses(): void
    {
        $sql = $this->invoke_get_sql(new \Doctrine\DBAL\Platforms\PostgreSQL120Platform(), 'e0_.uuid', '?');

        $this->assertSame('CASE WHEN LOWER(CAST(e0_.uuid AS TEXT)) LIKE ? THEN 1 ELSE 0 END', $sql);
    }

    #[Test]
    public function it_returns_an_integer_expression_safe_for_comparison_without_boolean_literals(): void
    {
        // The CASE WHEN form always returns 0 or 1, so comparing with an integer
        // literal (= 1 / = 0) is safe on all platforms regardless of
        // useBooleanTrueFalseStrings configuration.
        $pg = $this->invoke_get_sql(new PostgreSQLPlatform(), 'e0_.name', '?');
        $my = $this->invoke_get_sql(new MySQLPlatform(), 'e0_.name', '?');

        $this->assertStringStartsWith('CASE WHEN', $pg);
        $this->assertStringStartsWith('CASE WHEN', $my);
        $this->assertStringEndsWith('THEN 1 ELSE 0 END', $pg);
        $this->assertStringEndsWith('THEN 1 ELSE 0 END', $my);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function invoke_get_sql(object $platform, string $fieldSql, string $valueSql): string
    {
        $function    = new UxDataTablesSearchFunction('UX_DATATABLES_SEARCH');
        $columnRef   = $this->createMock(Node::class);
        $searchValue = $this->createMock(InputParameter::class);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $sqlWalker = $this->createMock(SqlWalker::class);
        $sqlWalker->method('walkSimpleArithmeticExpression')->with($columnRef)->willReturn($fieldSql);
        $sqlWalker->method('getConnection')->willReturn($connection);
        $searchValue->method('dispatch')->with($sqlWalker)->willReturn($valueSql);

        $ref = new \ReflectionClass($function);
        $ref->getProperty('columnRef')->setValue($function, $columnRef);
        $ref->getProperty('searchValue')->setValue($function, $searchValue);

        return $function->getSql($sqlWalker);
    }
}
