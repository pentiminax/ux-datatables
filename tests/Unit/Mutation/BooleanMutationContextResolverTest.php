<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Exception\InvalidBooleanMutationContextException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContext;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(BooleanMutationContextResolver::class)]
final class BooleanMutationContextResolverTest extends TestCase
{
    private const string TOKEN_SECRET = 'test-secret';

    /**
     * @return iterable<string, array{class-string<AbstractDataTable>, string, class-string}>
     */
    public static function provideResolvableContexts(): iterable
    {
        yield 'datatable entity class' => [SwitchableBooleanDataTableFixture::class, 'enabled', BooleanMutationEntityFixture::class];

        yield 'column entity class over the datatable entity class' => [ColumnEntityClassDataTableFixture::class, 'enabled', BooleanMutationColumnEntityFixture::class];

        yield 'column entity class when the datatable has none' => [ColumnOnlyEntityClassDataTableFixture::class, 'enabled', BooleanMutationColumnEntityFixture::class];

        yield 'effective toggle field differing from the column name' => [ToggleFieldDataTableFixture::class, 'isEnabled', BooleanMutationEntityFixture::class];

        yield 'column field fallback when the toggle field is empty' => [EmptyToggleFieldDataTableFixture::class, 'enabled', BooleanMutationEntityFixture::class];
    }

    /**
     * @param class-string<AbstractDataTable> $dataTableClass
     * @param class-string                    $expectedEntityClass
     */
    #[Test]
    #[DataProvider('provideResolvableContexts')]
    public function it_resolves_the_mutation_context_from_the_hmac_token(string $dataTableClass, string $field, string $expectedEntityClass): void
    {
        $context = $this->resolve($dataTableClass, $field);

        $this->assertSame($expectedEntityClass, $context->entityClass);
        $this->assertSame($dataTableClass, $context->dataTableClass);
        $this->assertSame($field, $context->field);
    }

    #[Test]
    public function it_rejects_an_unknown_datatable_token_before_any_mutation_context_is_created(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('Invalid DataTable token.');

        $this->resolver(SwitchableBooleanDataTableFixture::class)->resolve('not-a-valid-token', 'enabled');
    }

    #[Test]
    public function it_rejects_a_datatable_without_an_entity_class(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('must define an entity class');

        $this->resolve(MissingEntityClassDataTableFixture::class, 'enabled');
    }

    #[Test]
    public function it_rejects_boolean_columns_that_are_not_rendered_as_switches(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('is not a switchable boolean column');

        $this->resolve(NonSwitchBooleanDataTableFixture::class, 'enabled');
    }

    #[Test]
    public function it_rejects_non_boolean_columns(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('is not a switchable boolean column');

        $this->resolve(TextColumnDataTableFixture::class, 'enabled');
    }

    #[Test]
    public function it_rejects_a_field_that_does_not_match_the_switchable_column(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('is not a switchable boolean column');

        $this->resolve(SwitchableBooleanDataTableFixture::class, 'unknown');
    }

    #[Test]
    public function it_rejects_a_switchable_column_without_an_effective_field(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('is not a switchable boolean column');

        $this->resolve(MissingEffectiveFieldDataTableFixture::class, '');
    }

    /**
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    private function resolve(string $dataTableClass, string $field): BooleanMutationContext
    {
        return $this->resolver($dataTableClass)->resolve($this->token($dataTableClass), $field);
    }

    /**
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    private function resolver(string $dataTableClass): BooleanMutationContextResolver
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('table')->willReturn(new $dataTableClass());

        return new BooleanMutationContextResolver(new AjaxDataTableRegistry(
            $locator,
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [$dataTableClass => 'table'],
        ));
    }

    /**
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    private function token(string $dataTableClass): string
    {
        $token = (new AjaxDataTableRegistry(
            $this->createStub(ContainerInterface::class),
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [$dataTableClass => 'table'],
        ))->getBooleanMutationToken($dataTableClass);

        $this->assertNotNull($token);

        return $token;
    }
}

final class BooleanMutationEntityFixture
{
}

final class BooleanMutationColumnEntityFixture
{
}

#[AsDataTable(entityClass: BooleanMutationEntityFixture::class)]
final class SwitchableBooleanDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled')->renderAsSwitch();
    }
}

#[AsDataTable(entityClass: BooleanMutationEntityFixture::class)]
final class ColumnEntityClassDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled')
            ->setEntityClass(BooleanMutationColumnEntityFixture::class)
            ->renderAsSwitch();
    }
}

final class ColumnOnlyEntityClassDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled')
            ->setEntityClass(BooleanMutationColumnEntityFixture::class)
            ->renderAsSwitch();
    }
}

final class MissingEntityClassDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled')->renderAsSwitch();
    }
}

#[AsDataTable(entityClass: BooleanMutationEntityFixture::class)]
final class NonSwitchBooleanDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled');
    }
}

#[AsDataTable(entityClass: BooleanMutationEntityFixture::class)]
final class TextColumnDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('enabled');
    }
}

#[AsDataTable(entityClass: BooleanMutationEntityFixture::class)]
final class ToggleFieldDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled')
            ->setCustomOption(BooleanColumn::OPTION_TOGGLE_FIELD, 'isEnabled')
            ->renderAsSwitch();
    }
}

#[AsDataTable(entityClass: BooleanMutationEntityFixture::class)]
final class EmptyToggleFieldDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled')
            ->setCustomOption(BooleanColumn::OPTION_TOGGLE_FIELD, '')
            ->renderAsSwitch();
    }
}

#[AsDataTable(entityClass: BooleanMutationEntityFixture::class)]
final class MissingEffectiveFieldDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('')->renderAsSwitch();
    }
}
