<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ComposerRequirementsTest extends TestCase
{
    /**
     * The Ajax controllers map their request with #[MapRequestPayload], which fails with an HTTP 500
     * when the serializer is missing. It must be a hard requirement, not something an application,
     * or a dev dependency of this repository, happens to provide.
     */
    #[Test]
    public function it_requires_the_serializer_for_the_ajax_controllers(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('symfony/serializer', $composer['require']);
    }
}
