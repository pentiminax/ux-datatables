<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
