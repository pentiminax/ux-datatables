<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Exception;

use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\InvalidBooleanMutationContextException;
use Pentiminax\UX\DataTables\Exception\MutationException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * The MutationException parameter type pins the hierarchy the exception listener relies on.
     */
    #[Test]
    #[DataProvider('provideMutationExceptions')]
    public function it_maps_to_a_status_code_and_a_client_message(MutationException $exception, int $statusCode, string $clientMessage): void
    {
        $this->assertSame($statusCode, $exception->getStatusCode());
        $this->assertSame($clientMessage, $exception->getClientMessage());
    }

    /**
     * @return iterable<string, array{MutationException, int, string}>
     */
    public static function provideMutationExceptions(): iterable
    {
        yield 'entity not found' => [new EntityNotFoundException(), 404, 'Entity not found.'];

        yield 'property not writable' => [new PropertyNotWritableException('isEnabled'), 400, 'Unable to write "isEnabled" on the entity.'];

        yield 'mutation not allowed' => [new MutationNotAllowedException(), 403, 'You are not allowed to perform this action.'];

        yield 'invalid datatable token' => [InvalidBooleanMutationContextException::invalidDataTableToken(), 400, 'Invalid DataTable token.'];

        yield 'missing entity class' => [
            InvalidBooleanMutationContextException::missingEntityClass('App\\DataTable\\ProductDataTable'),
            400,
            'DataTable "App\\DataTable\\ProductDataTable" must define an entity class to mutate a boolean switch.',
        ];

        yield 'field not switchable' => [
            InvalidBooleanMutationContextException::fieldNotSwitchable('enabled', 'App\\DataTable\\ProductDataTable'),
            400,
            'Field "enabled" is not a switchable boolean column on DataTable "App\\DataTable\\ProductDataTable".',
        ];
    }
}
