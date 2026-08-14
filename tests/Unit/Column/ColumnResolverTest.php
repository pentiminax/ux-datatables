<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(ColumnResolver::class)]
final class ColumnResolverTest extends TestCase
{
    #[Test]
    #[DataProvider('provideEmptyResolutions')]
    public function it_resolves_no_column_without_usable_configuration(\Closure $resolve): void
    {
        $this->assertSame([], $resolve(new ColumnResolver()));
    }

    /**
     * @return iterable<string, array{\Closure(ColumnResolver): array<ColumnInterface>}>
     */
    public static function provideEmptyResolutions(): iterable
    {
        yield 'resolveColumns without attribute' => [static fn (ColumnResolver $resolver) => $resolver->resolveColumns(null)];
        yield 'columnsFromAttributes without attribute' => [static fn (ColumnResolver $resolver) => $resolver->columnsFromAttributes(null)];
        yield 'autoDetectColumns without detector' => [
            static fn (ColumnResolver $resolver) => $resolver->autoDetectColumns(new AsDataTable(entityClass: \stdClass::class)),
        ];
    }

    #[Test]
    #[TestWith([false])]
    #[TestWith([true])]
    public function auto_detect_returns_empty_when_detector_cannot_be_used(bool $apiPlatform): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector
            ->expects($apiPlatform ? $this->once() : $this->never())
            ->method('supports')
            ->willReturn(false);
        $detector->expects($this->never())->method('detectColumns');

        $resolver = new ColumnResolver(columnAutoDetector: $detector);

        $this->assertSame([], $resolver->autoDetectColumns(new AsDataTable(entityClass: \stdClass::class, apiPlatform: $apiPlatform)));
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

    /**
     * @param string[] $explicitGroups
     * @param string[] $expectedGroups
     */
    #[Test]
    #[TestWith([[], ['product:list']])]
    #[TestWith([['custom:group'], ['custom:group']])]
    public function auto_detect_forwards_serialization_groups(array $explicitGroups, array $expectedGroups): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(true);
        $detector
            ->expects($this->once())
            ->method('detectColumns')
            ->with(\stdClass::class, $expectedGroups)
            ->willReturn([]);

        $resolver = new ColumnResolver(columnAutoDetector: $detector);

        $resolver->autoDetectColumns(
            new AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'], apiPlatform: true),
            $explicitGroups
        );
    }

    #[Test]
    public function resolve_columns_falls_through_to_auto_detect(): void
    {
        $expected = [TextColumn::new('name', 'Name')];

        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(true);
        $detector->method('detectColumns')->willReturn($expected);

        // stdClass has no #[Column] attributes, so AttributeColumnReader returns []
        $resolver = new ColumnResolver(
            attributeColumnReader: new AttributeColumnReader(),
            columnAutoDetector: $detector,
        );

        $this->assertSame(
            $expected,
            $resolver->resolveColumns(new AsDataTable(entityClass: \stdClass::class, apiPlatform: true))
        );
    }

    #[Test]
    #[TestWith(['App\\Entity\\Product'])]
    #[TestWith([null])]
    public function configure_action_entity_class_only_applies_with_an_attribute(?string $entityClass): void
    {
        $resolver = new ColumnResolver();

        $actions = new Actions();
        $actions->add(Action::delete());
        $actions->add(Action::detail());

        $resolver->configureActionEntityClass(
            $actions,
            null === $entityClass ? null : new AsDataTable(entityClass: $entityClass)
        );

        foreach ($actions->getActions() as $action) {
            $serialized = $action->jsonSerialize();

            if (null === $entityClass) {
                $this->assertArrayNotHasKey('entityClass', $serialized);

                continue;
            }

            $this->assertSame($entityClass, $serialized['entityClass']);
        }
    }

    #[Test]
    public function filter_static_permissions_drops_denied_columns(): void
    {
        $resolver = $this->createResolverWithPermissions([
            ['ROLE_HR', null, false],
            ['ROLE_PUBLIC', null, true],
        ]);

        $salary = TextColumn::new('salary', 'Salary')->permission('ROLE_HR');
        $name   = TextColumn::new('name', 'Name');
        $public = TextColumn::new('public', 'Public')->permission('ROLE_PUBLIC');

        $filtered = $resolver->filterStaticPermissions([$salary, $name, $public]);

        $this->assertSame([$name, $public], $filtered);
    }

    #[Test]
    public function filter_static_permissions_filters_actions_inside_action_column(): void
    {
        $resolver = $this->createResolverWithPermissions([
            ['ROLE_ADMIN', null, false],
            ['ROLE_EDITOR', null, true],
        ]);

        $actions = new Actions();
        $actions->add(Action::delete()->permission('ROLE_ADMIN'));
        $actions->add(Action::edit()->permission('ROLE_EDITOR'));

        $filtered = $resolver->filterStaticPermissions([ActionColumn::fromActions('actions', '', $actions)]);

        $this->assertCount(1, $filtered);
        $this->assertSame(1, $actions->count());
        $this->assertSame(ActionType::Edit, $actions->getActions()[0]->getType());
    }

    #[Test]
    public function filter_static_permissions_drops_action_column_when_column_permission_denied(): void
    {
        $resolver = $this->createResolverWithPermissions([['ROLE_MANAGER', null, false]]);

        $actions = new Actions();
        $actions->add(Action::delete());
        $actionColumn = ActionColumn::fromActions('actions', '', $actions)->permission('ROLE_MANAGER');

        $this->assertSame([], $resolver->filterStaticPermissions([$actionColumn]));
    }

    #[Test]
    public function filter_actions_by_static_permissions_delegates_to_actions(): void
    {
        $resolver = $this->createResolverWithPermissions([['ROLE_ADMIN', null, false]]);

        $actions = new Actions();
        $actions->add(Action::delete()->permission('ROLE_ADMIN'));

        $resolver->filterActionsByStaticPermissions($actions);

        $this->assertTrue($actions->isEmpty());
    }

    #[Test]
    #[DataProvider('provideColumnsKeptWithoutPermissionCheck')]
    public function filter_static_permissions_keeps_columns_it_cannot_deny(ColumnInterface $column): void
    {
        $this->assertSame([$column], (new ColumnResolver())->filterStaticPermissions([$column]));
    }

    /**
     * @return iterable<string, array{ColumnInterface}>
     */
    public static function provideColumnsKeptWithoutPermissionCheck(): iterable
    {
        yield 'permission granted by the default checker' => [TextColumn::new('salary', 'Salary')->permission('ROLE_HR')];
        yield 'custom column without permission contract' => [self::createStub(ColumnInterface::class)];
    }

    /**
     * @param list<array{string, mixed, bool}> $isGrantedMap
     */
    private function createResolverWithPermissions(array $isGrantedMap): ColumnResolver
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willReturnMap($isGrantedMap);

        return new ColumnResolver(permissionChecker: new PermissionChecker($inner));
    }
}
