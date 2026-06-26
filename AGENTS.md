# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Symfony UX bundle (`pentiminax/ux-datatables`) integrating DataTables.net with server-side and client-side rendering. PHP 8.3+, Symfony 7.0/8.0. Frontend uses a Stimulus controller (TypeScript).

## Commands

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run a single test
vendor/bin/phpunit tests/Unit/Path/To/TestFile.php

# Fix code style (PSR-12 + Symfony rules)
composer fix

# Audit dependencies
composer audit
```

## Architecture

**Namespace**: `Pentiminax\UX\DataTables\` → `src/`
**Test namespace**: `Pentiminax\UX\DataTables\Tests\` → `tests/`

### Core Data Flow

```
Twig template (render_datatable) → Stimulus controller (assets/src/controller.ts)
  → Lazy-loads DataTables.net + extensions
  → Server-side: AJAX → DataTableRequest → QueryFilterChain → Doctrine ORM → DataTableResponseBuilder → JSON
  → Client-side: Data embedded directly
```

### Key Components

- **AbstractDataTable** (`src/Model/AbstractDataTable.php`): Base class users extend. Configured with `#[AsDataTable]` attribute. Defines columns, data provider, and extensions via `configure()` method.
- **DataTable** (`src/Model/DataTable.php`): Immutable configuration object with fluent builder API — `columns()`, `ajax()`, `serverSide()`, `responsive()`, etc.
- **Column system** (`src/Column/`): 12 column types extending `AbstractColumn` — TextColumn, NumberColumn, DateColumn, BooleanColumn, ActionColumn, etc. Serialized via `ColumnDto`.
- **Data Providers** (`src/DataProvider/`): `DoctrineDataProvider` (ORM QueryBuilder integration) and `ArrayDataProvider` (in-memory). Implement `DataProviderInterface`.
- **Query pipeline** (`src/Query/`): Chain-of-responsibility pattern. `QueryFilterChain` runs filters (GlobalSearchFilter, ColumnSearchFilter, OrderFilter) using 14 search strategies (Contains, StartsWith, Equal, GreaterThan, etc.).
- **Extensions** (`src/Model/Extensions/`): Buttons, Select, Responsive, Scroller, KeyTable, ColReorder, FixedColumns, ColumnControl. Each implements `ExtensionInterface`.
- **API Platform integration** (`src/ApiPlatform/`): Optional. Auto-detects columns from entity metadata, maps property types, resolves collection URLs.
- **Stimulus controller** (`assets/src/controller.ts`): Frontend entry point. Lazy-loads DataTables.net and extensions, handles delete actions and boolean toggling.
- **Maker** (`src/Maker/`): `make:datatable` Symfony console command to scaffold new DataTable classes.

### Patterns Used

- **Builder pattern**: DataTable fluent configuration
- **Chain of responsibility**: QueryFilterChain for query filtering/sorting
- **Strategy pattern**: SearchStrategy implementations in `src/Query/Strategy/`
- **Attributes**: `#[AsDataTable]` for service metadata
- **Generators**: Memory-efficient result iteration in DataTableResult

## Code Style

Enforced by PHP-CS-Fixer (`.php-cs-fixer.dist.php`): PSR-12, Symfony rules, single quotes, ordered imports, aligned binary operators. CI runs on PHP 8.3–8.5.

## Frontend

Package: `@pentiminax/ux-datatables` (ES module). Built TypeScript in `assets/src/`, compiled output in `assets/dist/`. Stimulus controller registered as `datatable`.

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **ux-datatables** (4936 symbols, 12964 relationships, 300 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> Index stale? Run `node .gitnexus/run.cjs analyze` from the project root — it auto-selects an available runner. No `.gitnexus/run.cjs` yet? `npx gitnexus analyze` (npm 11 crash → `npm i -g gitnexus`; #1939).

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows. For regression review, compare against the default branch: `detect_changes({scope: "compare", base_ref: "main"})`.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `query({search_query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `context({name: "symbolName"})`.
- For security review, `explain({target: "fileOrSymbol"})` lists taint findings (source→sink flows; needs `analyze --pdg`).

## Never Do

- NEVER edit a function, class, or method without first running `impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `rename` which understands the call graph.
- NEVER commit changes without running `detect_changes()` to check affected scope.

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/ux-datatables/context` | Codebase overview, check index freshness |
| `gitnexus://repo/ux-datatables/clusters` | All functional areas |
| `gitnexus://repo/ux-datatables/processes` | All execution flows |
| `gitnexus://repo/ux-datatables/process/{name}` | Step-by-step execution trace |

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
