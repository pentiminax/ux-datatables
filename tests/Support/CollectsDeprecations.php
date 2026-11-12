<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

/**
 * Collects the user deprecations a callable triggers, whatever the PHPUnit version.
 *
 * @internal
 */
trait CollectsDeprecations
{
    /**
     * Drops the notices about services built without an authorization checker, which hand-built
     * test fixtures raise without being what the test is about.
     *
     * @param list<string> $deprecations
     *
     * @return list<string>
     */
    protected function withoutAuthorizationFallback(array $deprecations): array
    {
        return array_values(array_filter($deprecations, static fn (string $message): bool => !str_contains($message, 'without an authorization checker')));
    }

    /**
     * @return list<string>
     */
    protected function deprecationsOf(callable $call): array
    {
        $deprecations = [];

        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $call();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }
}
