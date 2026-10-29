<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AjaxControllersSecurityGuardTest extends TestCase
{
    /**
     * Every Ajax controller must resolve its table from a signed token (through the registry or
     * the mutation context resolver); no configuration can bypass that.
     */
    #[Test]
    public function every_ajax_controller_resolves_its_table_from_a_signed_token(): void
    {
        $files = glob(__DIR__.'/../../../src/Controller/Ajax*Controller.php');

        self::assertNotEmpty($files);

        foreach ($files as $file) {
            self::assertMatchesRegularExpression(
                '/(registry->(get|resolveAction)|contextResolver->resolve)\(/',
                (string) file_get_contents($file),
                basename($file).' must resolve its table from a signed token.'
            );
        }
    }
}
