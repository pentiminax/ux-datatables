<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(UrlColumn::class)]
final class UrlColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_creates_html_type_column_with_only_the_url_marker(): void
    {
        $column = UrlColumn::new('website', 'Website');

        $this->assertColumnHeader($column, 'html', 'website', 'Website');
        $this->assertCustomOptions(['isUrl' => true], $column);
    }

    #[Test]
    #[DataProvider('provideCustomOptions')]
    public function it_serializes_custom_options(string $method, mixed $argument, string $option, mixed $expected): void
    {
        $column = UrlColumn::new('website')->{$method}($argument);

        $this->assertCustomOption($expected, $option, $column);
    }

    /**
     * @return iterable<string, array{string, mixed, string, mixed}>
     */
    public static function provideCustomOptions(): iterable
    {
        yield 'new tab' => ['openInNewTab', true, 'target', '_blank'];
        yield 'display value' => ['setDisplayValue', 'Visit', 'displayValue', 'Visit'];
        yield 'default protocol' => ['setDefaultProtocol', 'https', 'defaultProtocol', 'https'];
        yield 'default protocol is normalized' => ['setDefaultProtocol', '  HTTPS:  ', 'defaultProtocol', 'https'];
        yield 'allowed protocols' => ['allowedProtocols', ['http', 'https'], 'allowedProtocols', ['http', 'https']];
        yield 'allowed protocols are normalized and deduplicated' => ['allowedProtocols', ['HTTP', 'http', ' https: '], 'allowedProtocols', ['http', 'https']];
        yield 'external icon shown' => ['showExternalIcon', true, 'showExternalIcon', true];
        yield 'external icon hidden' => ['showExternalIcon', false, 'showExternalIcon', false];
        yield 'empty rendered as anchor' => ['renderEmptyAsAnchor', true, 'renderEmptyAsAnchor', true];
        yield 'empty not rendered as anchor' => ['renderEmptyAsAnchor', false, 'renderEmptyAsAnchor', false];
    }

    #[Test]
    #[TestWith(['renderEmptyAsAnchor'])]
    #[TestWith(['hasUrlResolver'])]
    public function it_omits_optional_custom_options_by_default(string $option): void
    {
        $this->assertNoCustomOption($option, UrlColumn::new('website'));
    }

    #[Test]
    #[DataProvider('provideInvalidProtocols')]
    public function it_rejects_invalid_protocols(string $method, mixed $argument, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        UrlColumn::new('website')->{$method}($argument);
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function provideInvalidProtocols(): iterable
    {
        yield 'empty default protocol' => ['setDefaultProtocol', '   ', 'Protocol cannot be empty.'];
        yield 'malformed default protocol' => ['setDefaultProtocol', 'http://', 'Invalid protocol format: "http://".'];
        yield 'unsafe default protocol' => ['setDefaultProtocol', 'JavaScript', 'Unsafe protocol "javascript" is not allowed.'];
        yield 'unsafe allowed protocol' => ['allowedProtocols', ['https', 'data'], 'Unsafe protocol "data" is not allowed.'];
    }

    #[Test]
    #[DataProvider('provideUrlResolvers')]
    public function it_resolves_url_without_a_generator(string|\Closure $url, ?string $expected): void
    {
        $column = UrlColumn::new('website')->linkToUrl($url);

        $this->assertSame($expected, $column->resolveUrl((object) ['id' => 7]));
        $this->assertTrue($column->hasUrlResolver());
        $this->assertNoCustomOption('url', $column);
        $this->assertCustomOption(true, 'hasUrlResolver', $column);
    }

    /**
     * @return iterable<string, array{string|\Closure, string|null}>
     */
    public static function provideUrlResolvers(): iterable
    {
        yield 'static url' => ['/users', '/users'];
        yield 'callable url' => [static fn (object $row): string => '/users/'.$row->id, '/users/7'];
        yield 'blank url is discarded' => [static fn (): string => '   ', null];
    }

    #[Test]
    #[DataProvider('provideRouteParameters')]
    public function it_resolves_route(\Closure|array $parameters, array $expectedParameters): void
    {
        $column = UrlColumn::new('website')->linkToRoute('app_user_show', $parameters);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('app_user_show', $expectedParameters)
            ->willReturn('/generated');

        $this->assertSame('/generated', $column->resolveUrl((object) ['id' => 7], $urlGenerator));
        $this->assertTrue($column->hasUrlResolver());
        $this->assertNoCustomOption('routeName', $column);
        $this->assertCustomOption(true, 'hasUrlResolver', $column);
    }

    /**
     * @return iterable<string, array{\Closure|array<string, mixed>, array<string, mixed>}>
     */
    public static function provideRouteParameters(): iterable
    {
        yield 'callable parameters' => [static fn (object $row): array => ['id' => $row->id], ['id' => 7]];
        yield 'array parameters' => [['type' => 'admin'], ['type' => 'admin']];
    }

    #[Test]
    public function it_fails_when_route_is_resolved_without_url_generator(): void
    {
        $column = UrlColumn::new('website')->linkToRoute('app_user_show');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('UrlGeneratorInterface is required to resolve UrlColumn routes.');

        $column->resolveUrl((object) ['id' => 7]);
    }
}
