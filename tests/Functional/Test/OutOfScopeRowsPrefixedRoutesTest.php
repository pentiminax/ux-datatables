<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Tests\Kernel\PrefixedHarnessAppKernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class OutOfScopeRowsPrefixedRoutesTest extends OutOfScopeRowsTestCase
{
    protected static function routePrefix(): string
    {
        return '/admin';
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new PrefixedHarnessAppKernel('test', true);
    }
}
