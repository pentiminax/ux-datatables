<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\Action;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Action::class)]
final class ActionTest extends TestCase
{
    public static function builtinActionProvider(): iterable
    {
        yield 'delete' => [Action::delete(), ActionType::Delete, 'Delete', 'btn btn-danger'];
        yield 'detail' => [Action::detail(), ActionType::Detail, 'Detail', 'btn btn-primary'];
        yield 'edit' => [Action::edit(), ActionType::Edit, 'Edit', 'btn btn-warning'];
        yield 'edit with custom label and class name' => [
            Action::edit('Modifier', 'btn btn-sm btn-warning'),
            ActionType::Edit,
            'Modifier',
            'btn btn-sm btn-warning',
        ];
    }

    #[Test]
    #[DataProvider('builtinActionProvider')]
    public function it_creates_builtin_actions_without_optional_state(Action $action, ActionType $type, string $label, string $className): void
    {
        $this->assertSame($type, $action->getType());
        $this->assertSame($type->value, $action->getName());
        $this->assertNull($action->getPosition());
        $this->assertFalse($action->isCollapsible());
        $this->assertNull($action->getCollapsibleTemplate());
        $this->assertSame([], $action->getCollapsibleParameters());
        $this->assertFalse($action->isAjaxRequest());

        $this->assertSame([
            'type'           => $type->value,
            'name'           => $type->value,
            'label'          => $label,
            'className'      => $className,
            'idField'        => 'id',
            'htmlAttributes' => [],
        ], $action->jsonSerialize());
    }

    /**
     * The serialized type is a cross-language contract: assets/src/controller.ts and
     * assets/src/columnRenderers/actionColumnRenderer.ts branch on these exact literals.
     */
    #[Test]
    public function it_serializes_the_action_types_the_frontend_branches_on(): void
    {
        $this->assertSame('DELETE', Action::delete()->jsonSerialize()['type']);
        $this->assertSame('DETAIL', Action::detail()->jsonSerialize()['type']);
        $this->assertSame('EDIT', Action::edit()->jsonSerialize()['type']);
    }

    #[Test]
    public function it_creates_a_custom_action_with_its_own_name(): void
    {
        $action = Action::new('download', 'Download', 'btn btn-link');

        $this->assertSame(ActionType::Custom, $action->getType());
        $this->assertSame('download', $action->getName());

        $this->assertSame([
            'type'           => 'CUSTOM',
            'name'           => 'download',
            'label'          => 'Download',
            'className'      => 'btn btn-link',
            'idField'        => 'id',
            'htmlAttributes' => [],
        ], $action->jsonSerialize());
    }

    #[Test]
    public function it_applies_fluent_setters(): void
    {
        $action = Action::delete()
            ->label('Supprimer')
            ->setClassName('btn btn-sm btn-danger')
            ->icon('bi bi-trash')
            ->askConfirmation('Are you sure?')
            ->htmlAttributes(['target' => '_blank'])
            ->setIdField('uuid')
            ->displayIf('isDeletable', true)
            ->setEntityClass('App\\Entity\\User');

        $this->assertSame('uuid', $action->getIdField());
        $this->assertSame('App\\Entity\\User', $action->getEntityClass());

        $json = $action->jsonSerialize();

        $this->assertSame('Supprimer', $json['label']);
        $this->assertSame('btn btn-sm btn-danger', $json['className']);
        $this->assertSame('bi bi-trash', $json['icon']);
        $this->assertSame('Are you sure?', $json['confirm']);
        $this->assertSame(['target' => '_blank'], $json['htmlAttributes']);
        $this->assertSame('uuid', $json['idField']);
        $this->assertSame(['field' => 'isDeletable', 'value' => true], $json['displayCondition']);
        $this->assertSame('App\\Entity\\User', $json['entityClass']);
    }

    #[Test]
    public function it_strips_the_leading_backslash_from_the_entity_class(): void
    {
        $action = Action::delete()->setEntityClass('\\App\\Entity\\User');

        $this->assertSame('App\\Entity\\User', $action->jsonSerialize()['entityClass']);
    }

    public static function iconProvider(): iterable
    {
        yield 'css class' => [['bi bi-pencil'], ['icon' => 'bi bi-pencil']];
        yield 'lucide enum' => [[Icon::Pencil], ['lucideIcon' => 'pencil']];
        yield 'lucide replaces css' => [['bi bi-pencil', Icon::Pencil], ['lucideIcon' => 'pencil']];
        yield 'css replaces lucide' => [[Icon::Pencil, 'bi bi-pencil'], ['icon' => 'bi bi-pencil']];
    }

    /**
     * @param list<Icon|string>     $icons
     * @param array<string, string> $expected
     */
    #[Test]
    #[DataProvider('iconProvider')]
    public function it_keeps_only_the_last_icon_call(array $icons, array $expected): void
    {
        $action = Action::edit();

        foreach ($icons as $icon) {
            $action->icon($icon);
        }

        $json = $action->jsonSerialize();

        $this->assertSame($expected, array_intersect_key($json, ['icon' => null, 'lucideIcon' => null]));
    }

    public static function urlProvider(): iterable
    {
        yield 'static url' => ['/books/42', ['id' => 42], '/books/42', '/books/42'];
        yield 'callable' => [static fn (object $row): string => '/books/'.$row->id, (object) ['id' => 42], '/books/42', null];
        yield 'blank callable' => [static fn (): string => '   ', ['id' => 42], null, null];
    }

    #[Test]
    #[DataProvider('urlProvider')]
    public function it_resolves_urls_per_row_and_serializes_static_ones_only(string|\Closure $url, mixed $row, ?string $resolved, ?string $serialized): void
    {
        $action = Action::detail()->linkToUrl($url);

        $this->assertSame($resolved, $action->resolveUrl($row));
        $this->assertSame($serialized, $action->jsonSerialize()['url'] ?? null);
    }

    public static function routeParametersProvider(): iterable
    {
        yield 'static parameters' => [['id' => 42], ['id' => 7], ['id' => 42]];
        yield 'callable parameters' => [static fn (object $row): array => ['id' => $row->id], (object) ['id' => 7], ['id' => 7]];
        yield 'no parameters' => [null, ['id' => 7], []];
    }

    /**
     * @param array<string, mixed>|null $params
     * @param array<string, mixed>      $expected
     */
    #[Test]
    #[DataProvider('routeParametersProvider')]
    public function it_resolves_route_parameters_per_row(array|\Closure|null $params, mixed $row, array $expected): void
    {
        $action = Action::new('publish', 'Publish')->linkToRoute('book_publish', $params);

        $this->assertSame('book_publish', $action->getRouteName());
        $this->assertSame($expected, $action->resolveRouteParameters($row));
    }

    #[Test]
    public function it_keeps_url_and_route_targets_mutually_exclusive(): void
    {
        $route = Action::new('publish', 'Publish')
            ->linkToUrl('/books/42/publish')
            ->linkToRoute('book_publish', ['id' => 42]);

        $this->assertSame('book_publish', $route->getRouteName());
        $this->assertNull($route->resolveUrl(['id' => 42]));
        $this->assertArrayNotHasKey('url', $route->jsonSerialize());

        $url = Action::new('publish', 'Publish')
            ->linkToRoute('book_publish', ['id' => 42])
            ->linkToUrl('/books/42/publish');

        $this->assertNull($url->getRouteName());
        $this->assertSame('/books/42/publish', $url->resolveUrl(['id' => 42]));
    }

    public static function ajaxMethodProvider(): iterable
    {
        yield 'defaults to POST' => [null, 'POST'];
        yield 'normalizes the case' => ['delete', 'DELETE'];
    }

    #[Test]
    #[DataProvider('ajaxMethodProvider')]
    public function it_normalizes_the_ajax_method(?string $method, string $expected): void
    {
        $action = null === $method
            ? Action::new('publish', 'Publish')->asAjaxRequest('publish_book')
            : Action::new('publish', 'Publish')->asAjaxRequest('publish_book', $method);

        $this->assertTrue($action->isAjaxRequest());
        $this->assertSame($expected, $action->getAjaxMethod());
        $this->assertSame($expected, $action->jsonSerialize()['ajaxMethod']);
        $this->assertSame('publish_book', $action->resolveCsrfTokenId(['id' => 7]));
    }

    #[Test]
    public function it_rejects_an_unsupported_ajax_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ajax action method must be "POST" or "DELETE", "PUT" given.');

        Action::new('publish', 'Publish')->asAjaxRequest('publish_book', 'PUT');
    }

    public static function csrfTokenIdProvider(): iterable
    {
        yield 'callable token id' => [static fn (object $row): string => 'publish_book_'.$row->id, (object) ['id' => 7], 'publish_book_7'];
        yield 'blank token id' => [static fn (): string => '   ', ['id' => 7], null];
        yield 'without ajax mode' => [null, ['id' => 7], null];
    }

    #[Test]
    #[DataProvider('csrfTokenIdProvider')]
    public function it_resolves_the_csrf_token_id_per_row(?\Closure $csrfTokenId, mixed $row, ?string $expected): void
    {
        $action = Action::new('publish', 'Publish');

        if (null !== $csrfTokenId) {
            $action->asAjaxRequest($csrfTokenId);
        }

        $this->assertSame(null !== $csrfTokenId, $action->isAjaxRequest());
        $this->assertSame($expected, $action->resolveCsrfTokenId($row));
    }

    #[Test]
    public function it_exposes_the_ajax_method_only_to_the_client(): void
    {
        $json = Action::new('publish', 'Publish')
            ->linkToRoute('book_publish', ['id' => 42])
            ->asAjaxRequest('publish_book')
            ->jsonSerialize();

        $this->assertSame('POST', $json['ajaxMethod']);
        $this->assertSame(['type', 'name', 'label', 'className', 'idField', 'htmlAttributes', 'ajaxMethod'], array_keys($json));
    }

    public static function permissionProvider(): iterable
    {
        yield 'static permission' => [null, true, false];
        yield 'per row permission' => [static fn (mixed $row): mixed => $row, false, true];
    }

    #[Test]
    #[DataProvider('permissionProvider')]
    public function it_distinguishes_static_from_per_row_permissions(?\Closure $subjectResolver, bool $static, bool $perRow): void
    {
        $action = Action::delete()->permission('ROLE_ADMIN', $subjectResolver);

        $this->assertSame('ROLE_ADMIN', $action->getPermission());
        $this->assertSame($subjectResolver, $action->getPermissionSubjectResolver());
        $this->assertSame($static, $action->hasStaticPermission());
        $this->assertSame($perRow, $action->hasPerRowPermission());

        $json = $action->jsonSerialize();

        $this->assertArrayNotHasKey('permission', $json);
        $this->assertArrayNotHasKey('permissionSubjectResolver', $json);
    }

    #[Test]
    public function it_serializes_the_collapsible_flag_without_leaking_the_template(): void
    {
        $action = Action::detail()->collapsible('book/detail.html.twig', ['extra' => 'value']);

        $this->assertTrue($action->isCollapsible());
        $this->assertSame('book/detail.html.twig', $action->getCollapsibleTemplate());
        $this->assertSame(['extra' => 'value'], $action->getCollapsibleParameters());

        $json = $action->jsonSerialize();

        $this->assertTrue($json['collapsible']);
        $this->assertArrayNotHasKey('collapsibleTemplate', $json);
        $this->assertArrayNotHasKey('collapsibleParameters', $json);
    }

    #[Test]
    public function it_overrides_the_position_without_serializing_it(): void
    {
        $action = Action::detail()->position(ActionsPosition::BeforeColumns);

        $this->assertSame(ActionsPosition::BeforeColumns, $action->getPosition());
        $this->assertArrayNotHasKey('position', $action->jsonSerialize());
    }
}
