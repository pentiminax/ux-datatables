<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class OutOfScopeRowsTest extends OutOfScopeRowsTestCase
{
    protected static function routePrefix(): string
    {
        return '';
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
