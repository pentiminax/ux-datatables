<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

/**
 * The temp directory a test kernel caches into: one per checkout, so a run never boots a
 * container compiled from another tree, and still warm across runs of the same tree.
 *
 * @internal
 */
final class KernelTempDir
{
    public static function of(string $group): string
    {
        return sys_get_temp_dir().'/'.$group.'/'.substr(md5(__DIR__), 0, 8);
    }
}
