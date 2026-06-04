<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\EventListener;

use Pentiminax\UX\DataTables\EventListener\MutationExceptionListener;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use PHPUnit\Framework\Attributes\CoversClass;
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
    public function it_maps_entity_not_found_to_a_404_json_response(): void
    {
        $event = $this->createEvent(new EntityNotFoundException());

        (new MutationExceptionListener())($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['success']);
        $this->assertSame('Entity not found.', $payload['message']);
    }

    #[Test]
    public function it_maps_property_not_writable_to_a_400_json_response(): void
    {
        $event = $this->createEvent(new PropertyNotWritableException('isEnabled'));

        (new MutationExceptionListener())($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function it_ignores_unrelated_exceptions(): void
    {
        $event = $this->createEvent(new \RuntimeException('boom'));

        (new MutationExceptionListener())($event);

        $this->assertNull($event->getResponse());
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
