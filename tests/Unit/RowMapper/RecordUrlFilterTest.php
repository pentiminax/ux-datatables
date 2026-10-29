<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper;

use Pentiminax\UX\DataTables\RowMapper\RecordUrlFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RecordUrlFilter::class)]
final class RecordUrlFilterTest extends TestCase
{
    #[Test]
    #[DataProvider('provideUrls')]
    public function it_keeps_only_safe_urls(mixed $url, ?string $expected): void
    {
        $this->assertSame($expected, RecordUrlFilter::filter($url));
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function provideUrls(): iterable
    {
        yield 'relative path' => ['/users/42?tab=1#top', '/users/42?tab=1#top'];
        yield 'root' => ['/', '/'];
        yield 'http' => ['http://example.com/a', 'http://example.com/a'];
        yield 'https uppercase' => ['HTTPS://example.com', 'HTTPS://example.com'];
        yield 'protocol relative' => ['//evil.example', null];
        yield 'backslash host' => ['/\\evil.example', null];
        yield 'backslash later' => ['/a\\b', null];
        yield 'javascript' => ['javascript:alert(1)', null];
        yield 'javascript mixed case' => ['JaVaScRiPt:alert(1)', null];
        yield 'javascript with tab' => ["java\tscript:alert(1)", null];
        yield 'leading space' => [' javascript:alert(1)', null];
        yield 'data' => ['data:text/html,<script>alert(1)</script>', null];
        yield 'vbscript' => ['vbscript:msgbox(1)', null];
        yield 'file' => ['file:///etc/passwd', null];
        yield 'ftp' => ['ftp://example.com', null];
        yield 'relative without slash' => ['users/42', null];
        yield 'http without host' => ['http://', null];
        yield 'newline in path' => ["/a\nb", null];
        yield 'trailing newline' => ["/a\n", null];
        yield 'null byte' => ["/a\0b", null];
        yield 'space in host' => ['http://a b', null];
        yield 'space in path' => ['/a b', null];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
        yield 'not a string' => [42, null];
    }
}
