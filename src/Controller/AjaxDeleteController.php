<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Pentiminax\UX\DataTables\Dto\AjaxDeleteRequestDto;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxDeleteController
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly ?MercureUpdatePublisher $mercurePublisher = null,
    ) {
    }

    public function __invoke(#[MapRequestPayload] AjaxDeleteRequestDto $payload): Response
    {
        $entityClass = $payload->entity;
        $manager     = $this->doctrine->getManagerForClass($entityClass);

        /** @var ObjectRepository<object> $repository */
        $repository = $manager?->getRepository($entityClass);
        $entity     = $repository->find($payload->id);

        if (!\is_object($entity)) {
            return $this->jsonError('Entity not found.', Response::HTTP_NOT_FOUND);
        }

        $manager?->remove($entity);
        $manager?->flush();

        if (null !== $this->mercurePublisher && [] !== $payload->topics) {
            $this->mercurePublisher->publish($payload->topics, [
                'type' => 'delete',
                'id'   => $payload->id,
            ]);
        }

        return new JsonResponse(['success' => true]);
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
