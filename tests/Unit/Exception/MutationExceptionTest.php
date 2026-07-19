<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Exception;

use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\InvalidBooleanMutationContextException;
use Pentiminax\UX\DataTables\Exception\MutationException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MutationException::class)]
#[CoversClass(EntityNotFoundException::class)]
#[CoversClass(InvalidBooleanMutationContextException::class)]
#[CoversClass(MutationNotAllowedException::class)]
#[CoversClass(PropertyNotWritableException::class)]
final class MutationExceptionTest extends TestCase
{
    #[Test]
    public function entity_not_found_maps_to_404_with_default_message(): void
    {
        $exception = new EntityNotFoundException();

        $this->assertInstanceOf(MutationException::class, $exception);
        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('Entity not found.', $exception->getClientMessage());
    }

    #[Test]
    public function property_not_writable_maps_to_400_with_field_message(): void
    {
        $exception = new PropertyNotWritableException('isEnabled');

        $this->assertInstanceOf(MutationException::class, $exception);
        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('Unable to write "isEnabled" on the entity.', $exception->getClientMessage());
    }

    #[Test]
    public function mutation_not_allowed_maps_to_403_with_default_message(): void
    {
        $exception = new MutationNotAllowedException();

        $this->assertInstanceOf(MutationException::class, $exception);
        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame('You are not allowed to perform this action.', $exception->getClientMessage());
    }

    #[Test]
    public function invalid_boolean_mutation_context_factories_map_to_400_with_specific_messages(): void
    {
        $invalidToken = InvalidBooleanMutationContextException::invalidDataTableToken();
        $missingClass = InvalidBooleanMutationContextException::missingEntityClass('App\\DataTable\\ProductDataTable');
        $invalidField = InvalidBooleanMutationContextException::fieldNotSwitchable('enabled', 'App\\DataTable\\ProductDataTable');

        $this->assertSame(400, $invalidToken->getStatusCode());
        $this->assertSame('Invalid DataTable token.', $invalidToken->getClientMessage());
        $this->assertSame(
            'DataTable "App\\DataTable\\ProductDataTable" must define an entity class to mutate a boolean switch.',
            $missingClass->getClientMessage(),
        );
        $this->assertSame(
            'Field "enabled" is not a switchable boolean column on DataTable "App\\DataTable\\ProductDataTable".',
            $invalidField->getClientMessage(),
        );
    }
}
