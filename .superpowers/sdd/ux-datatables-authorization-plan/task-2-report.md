# Task 2 Report — Enforce table/action permissions and row-action hiding

## Implementation

- Enforced `Permission::DT_ACCESS_TABLE` before Twig table hydration in `DataTablesExtension::renderDataTable()`.
- Enforced `Permission::DT_ACCESS_TABLE` when resolving Ajax read/action tokens in `AjaxDataTableRegistry::findByToken()`.
- Added `ResolvedDataTable::findAction(ActionType $type, bool $collapsible = false)` for the built-in endpoint action lookup.
- Replaced runtime action permission checks with `Permission::DT_EXECUTE_ACTION` and `ActionPermissionContext` in static action filtering, per-row action mapping, edit form handling, delete handling, and detail-row handling.
- Replaced legacy entity permissions in runtime mutation/detail paths with:
  - `Permission::DT_EDIT_ROW` for edit form submit and boolean toggles.
  - `Permission::DT_DELETE_ROW` for delete mutation.
  - `Permission::DT_VIEW_ROW_DETAILS` for detail-row fallback access.
- Added built-in endpoint configured-action enforcement:
  - Delete endpoint requires configured delete action before entity lookup.
  - Edit form view/submit require configured edit action before entity lookup.
  - Detail-row endpoint requires configured collapsible detail action before entity lookup.
- Preserved mutation ordering: CSRF, token/table access, configured action, entity lookup, row/action permission, mutation.
- Added row-scoped denial serialization under `__ux_datatables_denied_actions` without exposing action definitions as allowed actions.
- Updated the TypeScript action renderer to hide denied actions before row-id fallback.
- Rebuilt `assets/dist/` from `assets/src/`.

## Files changed

- `config/services.php`
- `src/Ajax/AjaxDataTableRegistry.php`
- `src/Ajax/DetailRowService.php`
- `src/Ajax/ResolvedDataTable.php`
- `src/Column/ColumnResolver.php`
- `src/Column/Rendering/ActionRowDataResolver.php`
- `src/Controller/AjaxDeleteController.php`
- `src/Controller/AjaxTemplateRenderController.php`
- `src/DependencyInjection/Compiler/DataTableRegistryPass.php`
- `src/Form/EditFormService.php`
- `src/Model/AbstractDataTable.php`
- `src/Model/Actions.php`
- `src/Mutation/EntityMutator.php`
- `src/RowMapper/RowProcessingPipeline.php`
- `src/Runtime/DataTableRuntimeFactory.php`
- `src/Twig/DataTablesExtension.php`
- `assets/src/columnRenderers/actionColumnRenderer.ts`
- `assets/src/columnRenderers/types.ts`
- `assets/dist/columnRenderers/actionColumnRenderer.js`
- `assets/dist/columnRenderers/actionColumnRenderer.js.map`
- Focused PHPUnit/Vitest fixtures and tests under `tests/` and `assets/test/`.

## TDD RED evidence

The initial RED commands and failure summaries were retained from the session state after resuming this task. The full raw terminal transcript for the earliest RED run was no longer available after resume, so this is the reconstructed evidence retained from the earlier command/output.

### RED: PHP authorization enforcement

Command:

```bash
vendor/bin/phpunit tests/Unit/Ajax/AjaxDataTableRegistryTest.php tests/Unit/Column/Rendering/ActionRowDataResolverTest.php
```

Observed output summary:

```text
.....FF.........FFFFF........ 29 / 29

Failures:
- AjaxDataTableRegistry read/action token tests expected Symfony\Component\Security\Core\Exception\AccessDeniedException; no exception was thrown.
- ActionRowDataResolver per-row/static/resolved-subject tests expected Permission::DT_EXECUTE_ACTION with ActionPermissionContext; actual checks still used legacy action attributes such as EDIT, ROLE_EDITOR, and OWNS.

FAILURES!
Tests: 29, Assertions: 52, Failures: 7.
```

### RED: frontend denied row action hiding

Command:

```bash
cd assets && npm test -- actionColumnRenderer.test.ts
```

Observed output summary:

```text
Test Files 1 failed (1)
Tests 1 failed | 41 passed (42)

Failed test:
hides a denied built-in action before falling back to the row id

Expected: ''
Received: '<button type="button" class="btn btn-danger" data-action-type="DELETE" data-id="42">Delete</button>'
```

Additional endpoint and Twig regression tests were added during resume after part of the production implementation already existed. They are regression coverage, not clean RED evidence.

## GREEN and verification evidence

### Focused PHP GREEN

Command:

```bash
vendor/bin/phpunit tests/Unit/Ajax/AjaxDataTableRegistryTest.php tests/Unit/Column/Rendering/ActionRowDataResolverTest.php
```

Output:

```text
OK (29 tests, 76 assertions)
```

### Focused frontend GREEN

Command:

```bash
cd assets && npm test -- actionColumnRenderer.test.ts
```

Output:

```text
Test Files 1 passed (1)
Tests 42 passed (42)
```

### Adjacent backend GREEN

Command:

```bash
vendor/bin/phpunit tests/Unit/Twig/DataTablesExtensionTest.php tests/Unit/Ajax/AjaxDataTableRegistryTest.php tests/Unit/Column/Rendering/ActionRowDataResolverTest.php tests/Unit/Mutation/EntityMutatorTest.php tests/Unit/Form/EditFormServiceTest.php tests/Unit/Ajax/DetailRowServiceTest.php tests/Unit/Controller/AjaxDeleteControllerTest.php tests/Unit/Controller/AjaxEditFormControllerTest.php tests/Unit/Controller/AjaxEditFormSubmitControllerTest.php tests/Unit/Controller/AjaxDetailControllerTest.php tests/Unit/Mutation/BooleanMutationContextResolverTest.php tests/Unit/Column/ColumnResolverTest.php tests/Unit/Model/ActionsTest.php tests/Unit/DependencyInjection/Compiler/DataTableRegistryPassTest.php
```

Output:

```text
OK (159 tests, 834 assertions)
```

### Mutation exception regression

Command:

```bash
vendor/bin/phpunit tests/Unit/Mutation/MutationExceptionHandlingTest.php
```

Output:

```text
OK (3 tests, 19 assertions)
```

### Full backend suite

Command:

```bash
vendor/bin/phpunit
```

Output:

```text
OK (1373 tests, 5749 assertions)
```

### PHP formatting

Command:

```bash
composer fix
```

Sandbox output:

```text
Failed to listen on "tcp://127.0.0.1:0": Operation not permitted (EPERM)
```

Escalated rerun output:

```text
PHP CS Fixer 3.95.25 Adalbertus by Fabien Potencier, Dariusz Ruminski and contributors.
PHP runtime: 8.4.1
Loaded config default from "/Users/tanguylemarie/.codex/worktrees/e889/ux-datatables/.php-cs-fixer.dist.php".
Fixed 2 of 399 files in 0.637 seconds, 42.00 MB memory used
```

The formatter also touched one unrelated alignment-only test file; that incidental diff was reverted before staging.

### Composer audit

Command:

```bash
composer audit
```

Sandbox output:

```text
curl error 6 while downloading https://repo.packagist.org/packages.json: Could not resolve host: repo.packagist.org
```

Escalated rerun output:

```text
No security vulnerability advisories found.
```

### Frontend lint/typecheck/test/build

Command:

```bash
cd assets && npm run lint && npm run typecheck && npm test && npm run build
```

Output summary:

```text
biome lint src/: Found 116 warnings. Exit code 0.
tsc --noEmit: exit code 0.
vitest run: Test Files 45 passed (45); Tests 496 passed (496).
tsc build: exit code 0.
```

### Whitespace check

Command:

```bash
git diff --check
```

Output:

```text
exit code 0
```

### Legacy runtime permission search

Command:

```bash
rg -n "isGranted\(\s*['\"](?:EDIT|DELETE|VIEW)|Permission::(?:EDIT|DELETE|VIEW)|PermissionChecker" src config tests --glob '*.php'
```

Output:

```text
tests/Unit/Security/AuthorizationCheckerTest.php:39:        $this->assertTrue($checker->isGranted('EDIT', new \stdClass()));
tests/Unit/Security/AuthorizationCheckerTest.php:53:        $this->assertTrue((new AuthorizationChecker($inner))->isGranted('EDIT', $subject));
tests/Unit/Security/AuthorizationCheckerTest.php:74:        $this->assertTrue((new AuthorizationChecker($inner))->isGranted('EDIT', $subject, $accessDecision));
```

Only adapter compatibility tests retain raw legacy strings; no runtime `src/` usage remains.

## GitNexus

Additional impacts run before editing symbols not covered by the parent pre-run:

- `ResolvedDataTable`: CRITICAL, 40 upstream hits.
- `RowProcessingPipeline`: CRITICAL, 35 upstream hits.
- `DataTableRegistryPass`: MEDIUM.
- `TestAuthorizationChecker`: LOW.

Change detection before commit:

```text
Changes: 35 files, 94 symbols
Affected processes: 116
Risk level: critical
```

The CRITICAL risk is expected for this task because authorization enforcement crosses rendering, Ajax registry, row mapping, and mutation execution boundaries.

## Self-review

- Table access is checked before client-side Twig hydration and during Ajax token resolution.
- Built-in delete/edit/detail endpoints now require configured actions before entity lookup, so missing/static denials do not leak entity lookup behavior.
- Row-scoped denials occur after entity lookup and before mutation/detail rendering.
- Boolean toggles remain column-driven and use `DT_EDIT_ROW`.
- Row-action hiding uses a separate internal denied-action list and filters before row-id fallback.
- Service constructor changes are optional where needed to preserve backward compatibility for direct instantiation tests and downstream consumers.
- `assets/dist/` was rebuilt from source, not edited manually.

## Concerns

- Biome lint exits 0 but still reports 116 pre-existing `noExplicitAny` warnings in unrelated frontend renderer files.
- `composer fix` was run with PHP 8.4.1 and emitted the repository warning that the project minimum is PHP 8.3.
- The JetBrains MCP tools referenced by the local pre-commit skill were not available in this session; repository formatting was performed with `composer fix`.
- Earliest raw RED logs were not available after resume; reconstructed RED evidence is included from retained session output summaries.
