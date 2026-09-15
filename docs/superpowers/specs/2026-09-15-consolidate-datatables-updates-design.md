# Consolidate DataTables dependency updates

## Goal

Replace the five open DataTables Dependabot pull requests with one coherent dependency update.

## Scope

Update every occurrence of these DataTables extension families to the versions proposed by the
open pull requests:

- Buttons to 4.0.3.
- Responsive to 4.0.3.
- Select to 4.0.1.
- ColumnControl to 2.0.2.
- ColReorder to 3.0.2.

Keep each family aligned across the `symfony.importmap`, `peerDependencies`, and `devDependencies`
sections of `assets/package.json`, including the Bootstrap, Bootstrap 4, Bootstrap 5, DataTables,
and CSS import variants that are already declared. Regenerate `assets/package-lock.json` through
npm. Do not update unrelated packages or add dependencies.

## Compatibility and verification

The changes stay within the currently declared major-version ranges. Verify the resulting package
graph with npm, then run the frontend lint, typecheck, tests, and build from `assets/`. Commit the
rebuilt `assets/dist/` output only if the dependency updates change it.

## Delivery

Open one pull request from a dedicated branch. The pull request will list the five superseded
Dependabot pull requests so they can be closed after the consolidated change is available.
