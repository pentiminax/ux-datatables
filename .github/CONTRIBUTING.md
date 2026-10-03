# UX DataTables Contribution Guidelines

Thank you for your interest in contributing to UX DataTables!

Contributions of all kinds are welcome: bug reports, documentation improvements, bug fixes, new features, tests, and ideas.

## Before Contributing

Please read our [Code of Conduct](CODE_OF_CONDUCT.md).

All contributions must:

- be written in English;
- follow the project's existing conventions;
- remain focused and reasonably scoped;
- include tests when changing behavior;
- preserve backward compatibility whenever possible.

UX DataTables is released under the [MIT License](../LICENSE).

## Reporting Bugs

Before opening an issue:

1. Check the [existing issues](https://github.com/pentiminax/ux-datatables/issues) to make sure the problem has not already been reported.
2. Make sure you are using a currently supported version of UX DataTables.
3. Try to reproduce the issue with the smallest possible example.

When reporting a bug, please include:

- the UX DataTables version;
- the PHP version;
- the Symfony version;
- the DataTables version when relevant;
- steps to reproduce the issue;
- the expected behavior;
- the actual behavior;
- relevant logs, exceptions, or code samples.

Security vulnerabilities must **not** be reported through public issues. Please follow our [Security Policy](SECURITY.md).

## Suggesting Features

Feature requests are welcome.

Before implementing a significant feature, consider opening an issue first so the API and implementation approach can be discussed.

Please describe:

- the problem you are trying to solve;
- the proposed behavior;
- a concrete use case;
- possible alternatives when relevant.

UX DataTables aims to keep its public API predictable and backward compatible, so additions should integrate naturally with the existing column, filter, action, extension, and data-provider APIs.

## Development Setup

Requirements:

- PHP 8.3 or later;
- Composer;
- Node.js 22 or later for frontend development;
- npm.

Install PHP dependencies:

```bash
composer install
```

If you are working on the frontend:

```bash
cd assets
npm ci
```

If you are working on the documentation:

```bash
cd docs
npm ci
```

## Pull Requests

To contribute code:

1. Fork the repository.
2. Create a branch from `main`.
3. Make your changes.
4. Add or update tests when applicable.
5. Update documentation when changing user-facing behavior.
6. Run the relevant checks locally.
7. Push your branch.
8. Open a pull request against `main`.

Keep pull requests focused on one concern whenever possible.

Clear and descriptive commit messages are appreciated.

## PHP Changes

Run the test suite:

```bash
vendor/bin/phpunit
```

Check dependencies for known vulnerabilities:

```bash
composer audit
```

Check coding standards:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

You can automatically fix coding-style issues with:

```bash
composer fix
```

Review the resulting diff before committing.

The CI currently tests supported PHP code against PHP 8.3, 8.4, and 8.5.

## Frontend Changes

Frontend sources are located in `assets/src/`.

Run the following commands from the `assets/` directory:

```bash
npm run typecheck
npm test
npm run lint
npm run build
```

The compiled files in `assets/dist/` are versioned.

Do not edit them manually. Update the source files in `assets/src/`, run:

```bash
npm run build
```

and commit the generated changes when required.

## Documentation

Documentation is located in the `docs/` directory.

When adding or changing a public feature, please update the corresponding documentation.

From `docs/`, run:

```bash
npm run lint
npm run build
```

before submitting documentation changes.

## Tests

Behavior changes should normally include tests covering:

- the expected behavior;
- relevant edge cases;
- regressions when fixing a bug.

You can run a specific PHPUnit test file:

```bash
vendor/bin/phpunit tests/Unit/Path/To/TestFile.php
```

or filter a specific test:

```bash
vendor/bin/phpunit --filter=test_name
```

## Backward Compatibility

UX DataTables is used as a library by Symfony applications, so backward compatibility is important.

Changes to public APIs should preferably be additive.

When changing an existing public behavior or API:

- avoid unnecessary breaking changes;
- provide a migration path when possible;
- use deprecations before