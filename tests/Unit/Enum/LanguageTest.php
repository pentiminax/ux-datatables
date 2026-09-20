<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Enum;

use Pentiminax\UX\DataTables\Enum\Language;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LanguageTest extends TestCase
{
    #[Test]
    public function it_builds_a_datatables_3_translation_url(): void
    {
        self::assertSame(
            'https://cdn.datatables.net/plug-ins/3.0.1/i18n/fr-FR.json',
            Language::FR->getUrl(),
        );
    }
}
