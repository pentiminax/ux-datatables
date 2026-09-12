<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;

/**
 * Single source of the guarded flush shared by the mutation and edit-form write paths.
 */
final class MutationFlusher
{
    /**
     * @throws MutationPersistenceException when the underlying persistence layer rejects the flush
     */
    public function flush(ObjectManager $manager): void
    {
        try {
            $manager->flush();
        } catch (DBALException|OptimisticLockException $exception) {
            // The 409 primarily targets constraint/conflict cases — a unique
            // violation or an optimistic-lock version mismatch. Broader DBAL
            // failures (e.g. a lost connection) are deliberately mapped here
            // too rather than leaking as a raw 500.
            throw new MutationPersistenceException(previous: $exception);
        }
    }
}
