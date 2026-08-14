<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column\Rendering;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @internal
 */
#[CoversClass(ActionRowDataResolver::class)]
final class ActionRowDataResolverTest extends TestCase
{
    #[Test]
    public function row_unchanged_when_no_action_column(): void
    {
        $row = ['id' => 1];

        $result = (new ActionRowDataResolver())->resolveRow($row, (object) ['id' => 1], [TextColumn::new('name', 'Name')]);

        $this->assertSame($row, $result);
    }

    /**
     * @param Action[]              $actions
     * @param ?array<string, mixed> $expected null when the row must not expose any action
     */
    #[Test]
    #[DataProvider('provideActionsToMap')]
    public function it_maps_resolvable_actions_into_the_row(array $actions, mixed $sourceRow, ?array $expected): void
    {
        $result = $this->resolveRow(new ActionRowDataResolver(), $sourceRow, ...$actions);

        $this->assertSame($expected, $result[ActionRowDataResolver::ROW_ACTIONS_KEY] ?? null);
    }

    /**
     * @return iterable<string, array{Action[], mixed, ?array<string, mixed>}>
     */
    public static function provideActionsToMap(): iterable
    {
        yield 'built-in action keyed by type' => [
            [Action::detail()->linkToUrl(static fn (object $r) => '/items/'.$r->id)],
            (object) ['id' => 7],
            ['DETAIL' => ['url' => '/items/7']],
        ];

        yield 'custom actions keyed by name' => [
            [
                Action::new('view', 'View')->linkToUrl(static fn (object $r) => '/invoices/'.$r->id),
                Action::new('download', 'Download')->linkToUrl(static fn (object $r) => '/invoices/'.$r->id.'/download'),
            ],
            (object) ['id' => 7],
            [
                'view'     => ['url' => '/invoices/7'],
                'download' => ['url' => '/invoices/7/download'],
            ],
        ];

        yield 'collapsible detail action exposes the id' => [
            [Action::detail()->collapsible('book/detail.html.twig')],
            (object) ['id' => 7],
            ['DETAIL' => ['id' => 7]],
        ];

        yield 'id read from the default entity identifier' => [
            [Action::edit()],
            new ActionRowDataResolverEntity(7),
            ['EDIT' => ['id' => 7]],
        ];

        yield 'id read from a custom entity identifier' => [
            [Action::delete()->setIdField('uuid')],
            new ActionRowDataResolverEntity(7, 'abc-123'),
            ['DELETE' => ['id' => 'abc-123']],
        ];

        yield 'id read from an array source row' => [
            [Action::delete()],
            ['id'     => 9],
            ['DELETE' => ['id' => 9]],
        ];

        // A PermissionChecker without inner checker grants everything.
        yield 'per row permission without authorization checker' => [
            [
                Action::edit()
                    ->linkToUrl(static fn (object $r) => '/items/'.$r->id)
                    ->permission('EDIT', static fn ($r) => $r),
            ],
            (object) ['id' => 7],
            ['EDIT' => ['url' => '/items/7', 'id' => 7]],
        ];

        yield 'action with neither url nor readable id' => [
            [Action::edit()->setIdField('missing')],
            new ActionRowDataResolverEntity(7),
            null,
        ];
    }

    #[Test]
    #[TestWith([true])]
    #[TestWith([false])]
    public function per_row_permission_decides_action_exposure(bool $granted): void
    {
        $sourceRow = (object) ['id' => 7];

        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner
            ->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', $sourceRow)
            ->willReturn($granted);

        $action = Action::edit()
            ->linkToUrl(static fn (object $r) => '/items/'.$r->id.'/edit')
            ->permission('EDIT', static fn ($r) => $r);

        $result = $this->resolveRow(new ActionRowDataResolver(new PermissionChecker($inner)), $sourceRow, $action);

        $this->assertSame(
            $granted ? ['EDIT' => ['url' => '/items/7/edit', 'id' => 7]] : null,
            $result[ActionRowDataResolver::ROW_ACTIONS_KEY] ?? null,
        );
    }

    #[Test]
    public function passes_resolved_subject_to_authorization_checker(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner
            ->expects($this->once())
            ->method('isGranted')
            ->with('OWNS', 'alice')
            ->willReturn(true);

        $action = Action::edit()
            ->linkToUrl(static fn (object $r) => '/items/'.$r->owner)
            ->permission('OWNS', static fn (object $r) => $r->owner);

        $this->resolveRow(new ActionRowDataResolver(new PermissionChecker($inner)), (object) ['owner' => 'alice'], $action);
    }

    #[Test]
    #[DataProvider('provideRouteParameters')]
    public function generates_route_url(array|\Closure $routeParameters): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('book_publish', ['id' => 42])
            ->willReturn('/books/42/publish');

        $action = Action::new('publish', 'Publish')->linkToRoute('book_publish', $routeParameters);

        $result = $this->resolveRow(
            new ActionRowDataResolver(null, null, $urlGenerator),
            (object) ['id' => 42],
            $action,
        );

        $this->assertSame(
            ['publish' => ['url' => '/books/42/publish']],
            $result[ActionRowDataResolver::ROW_ACTIONS_KEY],
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>|\Closure}>
     */
    public static function provideRouteParameters(): iterable
    {
        yield 'static parameters' => [['id' => 42]];
        yield 'per row parameters' => [static fn (object $row): array => ['id' => $row->id]];
    }

    #[Test]
    #[TestWith([false])]
    #[TestWith([true])]
    public function skips_route_action_when_url_cannot_be_generated(bool $withFailingRouter): void
    {
        $urlGenerator = null;

        if ($withFailingRouter) {
            $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
            $urlGenerator->method('generate')->willThrowException(new RouteNotFoundException());
        }

        $result = $this->resolveRow(
            new ActionRowDataResolver(null, null, $urlGenerator),
            (object) ['id' => 42],
            Action::new('publish', 'Publish')->linkToRoute('book_publish', ['id' => 42]),
        );

        $this->assertArrayNotHasKey(ActionRowDataResolver::ROW_ACTIONS_KEY, $result);
    }

    #[Test]
    public function adds_csrf_token_to_ajax_action(): void
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects($this->once())
            ->method('getToken')
            ->with('publish_book_42')
            ->willReturn(new CsrfToken('publish_book_42', 'token-value'));

        $action = Action::new('publish', 'Publish')
            ->linkToUrl('/books/42/publish')
            ->asAjaxRequest(static fn (object $row): string => 'publish_book_'.$row->id);

        $result = $this->resolveRow(
            new ActionRowDataResolver(null, null, null, $csrfTokenManager),
            (object) ['id' => 42],
            $action,
        );

        $this->assertSame(
            ['publish' => ['url' => '/books/42/publish', 'token' => 'token-value']],
            $result[ActionRowDataResolver::ROW_ACTIONS_KEY],
        );
    }

    #[Test]
    #[TestWith([false])]
    #[TestWith([true])]
    public function skips_ajax_action_when_csrf_token_is_unavailable(bool $withFailingTokenManager): void
    {
        $csrfTokenManager = null;

        if ($withFailingTokenManager) {
            $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
            $csrfTokenManager->method('getToken')->willThrowException(new SessionNotFoundException());
        }

        $action = Action::new('publish', 'Publish')
            ->linkToUrl('/books/42/publish')
            ->asAjaxRequest('publish_book');

        $result = $this->resolveRow(
            new ActionRowDataResolver(null, null, null, $csrfTokenManager),
            (object) ['id' => 42],
            $action,
        );

        $this->assertArrayNotHasKey(ActionRowDataResolver::ROW_ACTIONS_KEY, $result);
    }

    #[Test]
    public function skips_ajax_action_when_url_cannot_be_resolved(): void
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('getToken');

        $result = $this->resolveRow(
            new ActionRowDataResolver(null, null, null, $csrfTokenManager),
            (object) ['id' => 42],
            Action::new('publish', 'Publish')->asAjaxRequest('publish_book'),
        );

        $this->assertArrayNotHasKey(ActionRowDataResolver::ROW_ACTIONS_KEY, $result);
    }

    private function resolveRow(ActionRowDataResolver $resolver, mixed $sourceRow, Action ...$actions): array
    {
        $collection = new Actions();

        foreach ($actions as $action) {
            $collection->add($action);
        }

        return $resolver->resolveRow([], $sourceRow, [ActionColumn::fromActions('actions', '', $collection)]);
    }
}

final class ActionRowDataResolverEntity
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid = 'entity-uuid',
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
