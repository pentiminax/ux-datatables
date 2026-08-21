<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Doctrine\Function;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\InputParameter;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL function: UX_DATATABLES_SEARCH(columnRef, searchValue).
 *
 * Returns an integer (1 = match, 0 = no match) wrapping a case-insensitive,
 * platform-aware LIKE predicate:
 *
 *   PostgreSQL : CASE WHEN LOWER(CAST(field AS TEXT)) LIKE ? THEN 1 ELSE 0 END
 *   MySQL / MariaDB / SQLite : CASE WHEN LOWER(field) LIKE ? THEN 1 ELSE 0 END
 *
 * Returning an integer instead of a raw boolean expression avoids a type mismatch
 * on PostgreSQL: LIKE yields a native boolean there, and comparing it with the
 * integer that Doctrine emits for TRUE/FALSE literals (when
 * useBooleanTrueFalseStrings is disabled) would cause a runtime type error.
 * An integer return is unambiguously comparable with 1 / 0 on every platform.
 *
 * Use it in DQL WHERE as:
 *
 *   UX_DATATABLES_SEARCH(e.id, :param) = 1   -- field matches (LIKE)
 *   UX_DATATABLES_SEARCH(e.id, :param) = 0   -- field does not match (NOT LIKE)
 *
 * The call site is responsible for lower-casing and %-wrapping the bound
 * parameter value, e.g. '%' . mb_strtolower($value) . '%'.
 *
 * Casting to TEXT on PostgreSQL is intentional: it makes the predicate safe for
 * any field type that passes the supportsTextSearch() gate (including uuid/guid),
 * while CAST(text AS TEXT) is a harmless no-op for ordinary string columns.
 */
final class UxDataTablesSearchFunction extends FunctionNode
{
    private Node $columnRef;

    private InputParameter $searchValue;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->columnRef = $parser->StringPrimary();

        $parser->match(TokenType::T_COMMA);

        $this->searchValue = $parser->InputParameter();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $field    = $sqlWalker->walkSimpleArithmeticExpression($this->columnRef);
        $value    = $this->searchValue->dispatch($sqlWalker);
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            return \sprintf('CASE WHEN LOWER(CAST(%s AS TEXT)) LIKE %s THEN 1 ELSE 0 END', $field, $value);
        }

        return \sprintf('CASE WHEN LOWER(%s) LIKE %s THEN 1 ELSE 0 END', $field, $value);
    }
}
