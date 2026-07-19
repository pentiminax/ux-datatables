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
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use PHPUnit\Framework\Attributes\CoversClass;
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

    #[Test]
    public function it_resolves_entity_and_datatable_classes_from_the_hmac_token(): void
    {
        $context = $this->resolver(new SwitchableBooleanDataTableFixture())
            ->resolve($this->token(SwitchableBooleanDataTableFixture::class), 'enabled');

        $this->assertSame(BooleanMutationEntityFixture::class, $context->entityClass);
        $this->assertSame(SwitchableBooleanDataTableFixture::class, $context->dataTableClass);
        $this->assertSame('enabled', $context->field);
    }

    #[Test]
    public function it_rejects_an_unknown_datatable_token_before_any_mutation_context_is_created(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('Invalid DataTable token.');

        $this->resolver(new SwitchableBooleanDataTableFixture())->resolve('not-a-valid-token', 'enabled');
    }

    #[Test]
    public function it_rejects_a_datatable_without_an_entity_class(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('must define an entity class');

        $this->resolver(new MissingEntityClassDataTableFixture(), MissingEntityClassDataTableFixture::class)
            ->resolve($this->token(MissingEntityClassDataTableFixture::class), 'enabled');
    }

    #[Test]
    public function it_rejects_boolean_columns_that_are_not_rendered_as_switches(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('is not a switchable boolean column');

        $this->resolver(new NonSwitchBooleanDataTableFixture(), NonSwitchBooleanDataTableFixture::class)
            ->resolve($this->token(NonSwitchBooleanDataTableFixture::class), 'enabled');
    }

    #[Test]
    public function it_rejects_non_boolean_columns(): void
    {
        $this->expectException(InvalidBooleanMutationContextException::class);
        $this->expectExceptionMessage('is not a switchable boolean column');

        $this->resolver(new TextColumnDataTableFixture(), TextColumnDataTableFixture::class)
            ->resolve($this->token(TextColumnDataTableFixture::class), 'enabled');
    }

    #[Test]
    public function it_uses_the_effective_toggle_field_when_it_differs_from_the_column_name(): void
    {
        $context = $this->resolver(new ToggleFieldDataTableFixture(), ToggleFieldDataTableFixture::class)
            ->resolve($this->token(ToggleFieldDataTableFixture::class), 'isEnabled');

        $this->assertSame('isEnabled', $context->field);
    }

    #[Test]
    public function it_falls_back_to_the_column_field_when_toggle_field_is_empty(): void
    {
        $context = $this->resolver(new EmptyToggleFieldDataTableFixture(), EmptyToggleFieldDataTableFixture::class)
            ->resolve($this->token(EmptyToggleFieldDataTableFixture::class), 'enabled');

        $this->assertSame('enabled', $context->field);
    }

    /**
     * @param class-string<AbstractDataTable>|null $dataTableClass
     */
    private function resolver(AbstractDataTable $dataTable, ?string $dataTableClass = null): BooleanMutationContextResolver
    {
        $dataTableClass ??= $dataTable::class;

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('table')->willReturn($dataTable);

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

#[AsDataTable(entityClass: BooleanMutationEntityFixture::class)]
final class SwitchableBooleanDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled')->renderAsSwitch();
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
