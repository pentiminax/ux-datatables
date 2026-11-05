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
