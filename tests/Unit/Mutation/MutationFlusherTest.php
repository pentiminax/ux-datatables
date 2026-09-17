<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MutationFlusherTest extends TestCase
{
    #[Test]
    public function it_flushes_the_manager_when_the_persistence_layer_accepts_it(): void
    {
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects($this->once())->method('flush');

        (new MutationFlusher())->flush($manager);
    }

    #[Test]
    public function it_wraps_dbal_exceptions_into_a_mutation_persistence_exception(): void
    {
        $previous = self::dbalException();

        $this->assertMapsToPersistenceException($this->managerFailingWith($previous), $previous);
    }

    #[Test]
    public function it_wraps_optimistic_lock_exceptions(): void
    {
        $previous = OptimisticLockException::lockFailed(new \stdClass());

        $this->assertMapsToPersistenceException($this->managerFailingWith($previous), $previous);
    }

    #[Test]
    public function it_lets_other_exceptions_through(): void
    {
        $previous = new \RuntimeException('Unrelated failure.');

        $this->expectExceptionObject($previous);

        (new MutationFlusher())->flush($this->managerFailingWith($previous));
    }

    private static function dbalException(): DBALException
    {
        // A genuine Doctrine\DBAL\Exception subtype in both DBAL 3 and 4.
        $driverException = new class('constraint violation') extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return '23505';
            }
        };

        return new UniqueConstraintViolationException($driverException, null);
    }

    private function managerFailingWith(\Throwable $failure): ObjectManager
    {
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('flush')->willThrowException($failure);

        return $manager;
    }

    private function assertMapsToPersistenceException(ObjectManager $manager, \Throwable $expectedPrevious): void
    {
        $caught = null;

        try {
            (new MutationFlusher())->flush($manager);
        } catch (MutationPersistenceException $exception) {
            $caught = $exception;
        }

        $this->assertInstanceOf(MutationPersistenceException::class, $caught);
        $this->assertSame(409, $caught->getStatusCode());
        $this->assertSame('The operation could not be completed due to a data conflict.', $caught->getClientMessage());
        $this->assertSame($expectedPrevious, $caught->getPrevious());
    }
}
