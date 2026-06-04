<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Pentiminax\UX\DataTables\EventListener\MutationExceptionListener;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(DataTablesBundle::class)]
final class MutationServiceWiringTest extends TestCase
{
    #[Test]
    public function it_wires_entity_mutator_with_the_mercure_publisher_interface(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();

        $mutator = $kernel->getContainer()->get('test.datatables.mutation.mutator');

        $this->assertInstanceOf(EntityMutator::class, $mutator);

        $publisher = $this->readPrivateProperty($mutator, 'publisher');

        $this->assertInstanceOf(MercureUpdatePublisher::class, $publisher);

        $kernel->shutdown();
    }

    #[Test]
    public function it_registers_the_mutation_exception_listener_with_priority(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $kernel->getContainer()->get('event_dispatcher');

        $listeners  = $dispatcher->getListeners('kernel.exception');
        $priorities = array_map(
            static fn (callable $listener): int => $dispatcher->getListenerPriority('kernel.exception', $listener),
            $listeners,
        );

        $this->assertContains(10, $priorities);

        $listenerFound = false;

        foreach ($listeners as $listener) {
            if ($listener instanceof MutationExceptionListener || (\is_array($listener) && $listener[0] instanceof MutationExceptionListener)) {
                $listenerFound = true;

                break;
            }
        }

        $this->assertTrue($listenerFound, 'MutationExceptionListener must be registered on kernel.exception.');

        $kernel->shutdown();
    }

    #[Test]
    public function it_registers_the_null_mercure_publisher_fallback_service(): void
    {
        $kernel = new TwigAppKernel('test', true);
        $kernel->boot();

        $publisher = $kernel->getContainer()->get('test.datatables.mercure.null_publisher');

        $this->assertInstanceOf(NullMercurePublisher::class, $publisher);

        $kernel->shutdown();
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
