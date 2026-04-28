<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\StateExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StateExtension::class)]
final class StateExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_with_default_duration(): void
    {
        $extension = new StateExtension();

        $this->assertEquals(['duration' => 7200], $extension->jsonSerialize());
    }

    #[Test]
    public function it_configures_custom_duration(): void
    {
        $extension = new StateExtension();
        $extension->duration(3600);

        $this->assertEquals(3600, $extension->jsonSerialize()['duration']);
    }

    #[Test]
    public function it_configures_session_duration(): void
    {
        $extension = new StateExtension();
        $extension->duration(-1);

        $this->assertEquals(-1, $extension->jsonSerialize()['duration']);
    }

    #[Test]
    public function it_configures_session_storage(): void
    {
        $extension = new StateExtension();
        $extension->sessionStorage();

        $this->assertEquals(-1, $extension->jsonSerialize()['duration']);
    }

    #[Test]
    public function it_configures_indefinite_storage(): void
    {
        $extension = new StateExtension();
        $extension->indefinite();

        $this->assertEquals(0, $extension->jsonSerialize()['duration']);
    }

    #[Test]
    public function it_returns_correct_key(): void
    {
        $this->assertEquals('state', (new StateExtension())->getKey());
    }

    #[Test]
    public function it_accepts_duration_in_constructor(): void
    {
        $extension = new StateExtension(duration: 1800);

        $this->assertEquals(1800, $extension->jsonSerialize()['duration']);
    }
}
