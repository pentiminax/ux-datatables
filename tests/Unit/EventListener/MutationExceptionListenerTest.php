<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\EventListener;

use Pentiminax\UX\DataTables\EventListener\MutationExceptionListener;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\MutationException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(MutationExceptionListener::class)]
final class MutationExceptionListenerTest extends TestCase
{
    #[Test]
    #[DataProvider('provideMutationExceptions')]
    public function it_maps_a_mutation_exception_to_a_json_response(MutationException $exception, int $statusCode, string $message): void
    {
        $event = $this->createEvent($exception);

        (new MutationExceptionListener())($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => $message],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_ignores_unrelated_exceptions(): void
    {
        $event = $this->createEvent(new \RuntimeException('boom'));

        (new MutationExceptionListener())($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{MutationException, int, string}>
     */
    public static function provideMutationExceptions(): iterable
    {
        yield 'entity not found' => [new EntityNotFoundException(), 404, 'Entity not found.'];

        yield 'property not writable' => [new PropertyNotWritableException('isEnabled'), 400, 'Unable to write "isEnabled" on the entity.'];
    }

    private function createEvent(\Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );
    }
}
