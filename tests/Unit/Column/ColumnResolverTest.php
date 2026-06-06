<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(ColumnResolver::class)]
final class ColumnResolverTest extends TestCase
{
    #[Test]
    public function resolve_columns_returns_empty_when_no_attribute(): void
    {
        $resolver = new ColumnResolver();

        $this->assertSame([], $resolver->resolveColumns(null));
    }

    #[Test]
    public function columns_from_attributes_returns_empty_when_no_attribute(): void
    {
        $resolver = new ColumnResolver();

        $this->assertSame([], $resolver->columnsFromAttributes(null));
    }

    #[Test]
    public function auto_detect_returns_empty_without_detector(): void
    {
        $resolver = new ColumnResolver();

        $this->assertSame([], $resolver->autoDetectColumns(new AsDataTable(entityClass: \stdClass::class)));
    }

    #[Test]
    public function auto_detect_returns_empty_without_api_platform_opt_in(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->expects($this->never())->method('supports');
        $detector->expects($this->never())->method('detectColumns');

        $resolver = new ColumnResolver(columnAutoDetector: $detector);

        $this->assertSame([], $resolver->autoDetectColumns(new AsDataTable(entityClass: \stdClass::class)));
    }

    #[Test]
    public function auto_detect_returns_empty_when_not_supported(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(false);
        $detector->expects($this->never())->method('detectColumns');

        $resolver = new ColumnResolver(columnAutoDetector: $detector);

        $this->assertSame([], $resolver->autoDetectColumns(new AsDataTable(entityClass: \stdClass::class, apiPlatform: true)));
    }

    #[Test]
    public function auto_detect_returns_detected_columns(): void
    {
        $expected = [
            NumberColumn::new('id', 'ID'),
            TextColumn::new('name', 'Name'),
        ];

        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->with(\stdClass::class)->willReturn(true);
        $detector->method('detectColumns')->with(\stdClass::class, [])->willReturn($expected);

        $resolver = new ColumnResolver(columnAutoDetector: $detector);

        $this->assertSame($expected, $resolver->autoDetectColumns(new AsDataTable(entityClass: \stdClass::class, apiPlatform: true)));
    }

    #[Test]
    public function auto_detect_uses_attribute_serialization_groups(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(true);
        $detector
            ->expects($this->once())
            ->method('detectColumns')
            ->with(\stdClass::class, ['product:list'])
            ->willReturn([]);

        $resolver = new ColumnResolver(columnAutoDetector: $detector);

        $resolver->autoDetectColumns(
            new AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'], apiPlatform: true)
        );
    }

    #[Test]
    public function auto_detect_explicit_groups_override_attribute(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(true);
        $detector
            ->expects($this->once())
            ->method('detectColumns')
            ->with(\stdClass::class, ['custom:group'])
            ->willReturn([]);

        $resolver = new ColumnResolver(columnAutoDetector: $detector);

        $resolver->autoDetectColumns(
            new AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'], apiPlatform: true),
            ['custom:group']
        );
    }

    #[Test]
    public function configure_boolean_columns_sets_entity_class(): void
    {
        $resolver = new ColumnResolver();
        $attr     = new AsDataTable(entityClass: 'App\\Entity\\Product');

        $boolCol = BooleanColumn::new('active', 'Active');
        $textCol = TextColumn::new('name', 'Name');

        $resolver->configureBooleanColumns([$boolCol, $textCol], $attr);

        $this->assertSame('App\\Entity\\Product', $boolCol->getEntityClass());
    }

    #[Test]
    public function configure_boolean_columns_skips_when_entity_class_already_set(): void
    {
        $resolver = new ColumnResolver();
        $attr     = new AsDataTable(entityClass: 'App\\Entity\\Product');

        $boolCol = BooleanColumn::new('active', 'Active');
        $boolCol->setEntityClass('App\\Entity\\Order');

        $resolver->configureBooleanColumns([$boolCol], $attr);

        $this->assertSame('App\\Entity\\Order', $boolCol->getEntityClass());
    }

    #[Test]
    public function configure_boolean_columns_noop_when_no_attribute(): void
    {
        $resolver = new ColumnResolver();
        $boolCol  = BooleanColumn::new('active', 'Active');

        $resolver->configureBooleanColumns([$boolCol], null);

        $this->assertNull($boolCol->getEntityClass());
    }

    #[Test]
    public function configure_action_entity_class_sets_entity_on_actions(): void
    {
        $resolver = new ColumnResolver();
        $attr     = new AsDataTable(entityClass: 'App\\Entity\\Product');

        $actions = new Actions();
        $actions->add(Action::delete());
        $actions->add(Action::detail());

        $resolver->configureActionEntityClass($actions, $attr);

        foreach ($actions->getActions() as $action) {
            $serialized = $action->jsonSerialize();
            $this->assertSame('App\\Entity\\Product', $serialized['entityClass']);
        }
    }

    #[Test]
    public function configure_action_entity_class_noop_when_no_attribute(): void
    {
        $resolver = new ColumnResolver();

        $actions = new Actions();
        $actions->add(Action::delete());

        $resolver->configureActionEntityClass($actions, null);

        $serialized = $actions->getActions()[0]->jsonSerialize();
        $this->assertArrayNotHasKey('entityClass', $serialized);
    }

    #[Test]
    public function filter_static_permissions_drops_denied_columns(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willReturnMap([
            ['ROLE_HR', null, false],
            ['ROLE_PUBLIC', null, true],
        ]);

        $resolver = new ColumnResolver(permissionChecker: new PermissionChecker($inner));

        $salary = TextColumn::new('salary', 'Salary')->permission('ROLE_HR');
        $name   = TextColumn::new('name', 'Name');
        $public = TextColumn::new('public', 'Public')->permission('ROLE_PUBLIC');

        $filtered = $resolver->filterStaticPermissions([$salary, $name, $public]);

        $this->assertSame([$name, $public], $filtered);
    }

    #[Test]
    public function filter_static_permissions_filters_actions_inside_action_column(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willReturnMap([
            ['ROLE_ADMIN', null, false],
            ['ROLE_EDITOR', null, true],
        ]);

        $actions = new Actions();
        $actions->add(Action::delete()->permission('ROLE_ADMIN'));
        $actions->add(Action::edit()->permission('ROLE_EDITOR'));

        $actionColumn = ActionColumn::fromActions('actions', '', $actions);

        $resolver = new ColumnResolver(permissionChecker: new PermissionChecker($inner));
        $filtered = $resolver->filterStaticPermissions([$actionColumn]);

        $this->assertCount(1, $filtered);
        $this->assertSame(1, $actions->count());
        $this->assertSame(ActionType::Edit, $actions->getActions()[0]->getType());
    }

    #[Test]
    public function filter_static_permissions_drops_action_column_when_column_permission_denied(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willReturn(false);

        $actions = new Actions();
        $actions->add(Action::delete());
        $actionColumn = ActionColumn::fromActions('actions', '', $actions)->permission('ROLE_MANAGER');

        $resolver = new ColumnResolver(permissionChecker: new PermissionChecker($inner));
        $filtered = $resolver->filterStaticPermissions([$actionColumn]);

        $this->assertSame([], $filtered);
    }

    #[Test]
    public function filter_actions_by_static_permissions_delegates_to_actions(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willReturn(false);

        $actions = new Actions();
        $actions->add(Action::delete()->permission('ROLE_ADMIN'));

        $resolver = new ColumnResolver(permissionChecker: new PermissionChecker($inner));
        $resolver->filterActionsByStaticPermissions($actions);

        $this->assertTrue($actions->isEmpty());
    }

    #[Test]
    public function filter_static_permissions_is_noop_without_checker(): void
    {
        $resolver = new ColumnResolver();
        $column   = TextColumn::new('salary', 'Salary')->permission('ROLE_HR');

        $this->assertSame([$column], $resolver->filterStaticPermissions([$column]));
    }

    #[Test]
    public function filter_static_permissions_keeps_custom_column_without_permission_contract(): void
    {
        $resolver = new ColumnResolver(permissionChecker: new PermissionChecker());
        $column   = new class implements ColumnInterface {
            public function getName(): string
            {
                return 'custom';
            }

            public function getField(): ?string
            {
                return 'custom';
            }

            public function setField(string $field): static
            {
                return $this;
            }

            public function getOrderExpression(): ?string
            {
                return null;
            }

            public function setVisible(bool $visible): static
            {
                return $this;
            }

            public function isSearchable(): bool
            {
                return true;
            }

            public function isGlobalSearchable(): bool
            {
                return true;
            }

            public function getData(): ?string
            {
                return 'custom';
            }

            public function getTitle(): ?string
            {
                return 'Custom';
            }

            public function isNumber(): bool
            {
                return false;
            }

            public function isDate(): bool
            {
                return false;
            }

            public function getType(): ColumnType
            {
                return ColumnType::STRING;
            }

            public function isVisible(): bool
            {
                return true;
            }

            public function isOrderable(): bool
            {
                return true;
            }

            public function isExportable(): bool
            {
                return true;
            }

            public function getWidth(): ?string
            {
                return null;
            }

            public function getClassName(): ?string
            {
                return null;
            }

            public function getCellType(): ?string
            {
                return null;
            }

            public function getRender(): ?string
            {
                return null;
            }

            public function getDefaultContent(): ?string
            {
                return null;
            }

            public function getCustomOption(string $optionName): mixed
            {
                return null;
            }

            public function getCustomOptions(): array
            {
                return [];
            }

            public function jsonSerialize(): array
            {
                return ['name' => 'custom'];
            }
        };

        $this->assertSame([$column], $resolver->filterStaticPermissions([$column]));
    }

    #[Test]
    public function resolve_columns_falls_through_to_auto_detect(): void
    {
        $expected = [TextColumn::new('name', 'Name')];

        // stdClass has no #[Column] attributes, so AttributeColumnReader returns []
        $reader = new AttributeColumnReader();

        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(true);
        $detector->method('detectColumns')->willReturn($expected);

        $resolver = new ColumnResolver(
            attributeColumnReader: $reader,
            columnAutoDetector: $detector,
        );

        $this->assertSame(
            $expected,
            $resolver->resolveColumns(new AsDataTable(entityClass: \stdClass::class, apiPlatform: true))
        );
    }
}
