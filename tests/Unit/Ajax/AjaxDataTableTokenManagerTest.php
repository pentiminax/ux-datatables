<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AjaxDataTableTokenManager::class)]
final class AjaxDataTableTokenManagerTest extends TestCase
{
    #[Test]
    public function it_generates_deterministic_opaque_tokens(): void
    {
        $manager = new AjaxDataTableTokenManager('test-secret');

        $token = $manager->generateHmacSignature('App\\DataTable\\UserDataTable');

        $this->assertSame($token, $manager->generateHmacSignature('App\\DataTable\\UserDataTable'));
        $this->assertNotSame($token, $manager->generateHmacSignature('App\\DataTable\\AdminDataTable'));
        $this->assertStringNotContainsString('UserDataTable', $token);
        $this->assertStringNotContainsString('App', $token);
    }

    #[Test]
    public function it_rejects_empty_secrets(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AjaxDataTableTokenManager('');
    }
}
