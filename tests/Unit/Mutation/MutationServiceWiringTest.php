<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\DataTablesBundle;
use Pentiminax\UX\DataTables\EventListener\MutationExceptionListener;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @internal
 */
#[CoversClass(DataTablesBundle::class)]
final class MutationServiceWiringTest extends TestCase
{
    private TwigAppKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TwigAppKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    #[Test]
    public function it_wires_entity_mutator_with_the_mercure_publisher_interface(): void
    {
        $mutator = $this->service('test.datatables.mutation.mutator');

        $this->assertInstanceOf(EntityMutator::class, $mutator);
        $this->assertInstanceOf(MercureUpdatePublisher::class, $this->readPrivateProperty($mutator, 'publisher'));
    }

    #[Test]
    public function it_registers_the_mutation_exception_listener_with_priority(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->service('event_dispatcher');

        foreach ($dispatcher->getListeners('kernel.exception') as $listener) {
            if ($listener instanceof MutationExceptionListener || (\is_array($listener) && $listener[0] instanceof MutationExceptionListener)) {
                $this->assertSame(10, $dispatcher->getListenerPriority('kernel.exception', $listener));

                return;
            }
        }

        $this->fail('MutationExceptionListener must be registered on kernel.exception.');
    }

    #[Test]
    public function it_always_wires_the_mutation_token_validator_with_a_csrf_token_manager(): void
    {
        $validator = $this->service('test.datatables.security.mutation_token_validator');

        $this->assertInstanceOf(MutationTokenValidator::class, $validator);

        // The guard must never be left without a manager, even when the application
        // has no CSRF component configured: otherwise every mutation would be rejected.
        $this->assertInstanceOf(CsrfTokenManagerInterface::class, $this->readPrivateProperty($validator, 'csrfTokenManager'));
    }

    #[Test]
    public function it_registers_the_null_mercure_publisher_fallback_service(): void
    {
        $this->assertInstanceOf(NullMercurePublisher::class, $this->service('test.datatables.mercure.null_publisher'));
    }

    #[Test]
    public function it_wires_the_ajax_edit_controller_with_the_boolean_mutation_context_resolver(): void
    {
        $controller = $this->service('datatables.controller.ajax_edit');

        $this->assertInstanceOf(AjaxEditController::class, $controller);
        $this->assertInstanceOf(BooleanMutationContextResolver::class, $this->readPrivateProperty($controller, 'contextResolver'));
    }

    private function service(string $id): object
    {
        $service = $this->kernel->getContainer()->get($id);

        $this->assertIsObject($service);

        return $service;
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
