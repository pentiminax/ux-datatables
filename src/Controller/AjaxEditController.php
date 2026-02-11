<?php

namespace Pentiminax\UX\DataTables\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Pentiminax\UX\DataTables\Dto\AjaxEditRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class AjaxEditController
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function __invoke(#[MapRequestPayload] AjaxEditRequestDto $payload): Response
    {
        $entityClass = $payload->entity;
        $field       = $payload->field;
        $manager     = $this->doctrine->getManagerForClass($entityClass);

        /** @var ObjectRepository<object> $repository */
        $repository = $manager?->getRepository($entityClass);
        $id         = $payload->id;
        $entity     = $repository->find($id);

        if (!\is_object($entity)) {
            return $this->jsonError('Entity not found.', Response::HTTP_NOT_FOUND);
        }

        $newValue = $payload->newValue;

        if (!$this->updateProperty($entity, $field, $newValue)) {
            return $this->jsonError(
                \sprintf('Unable to write "%s" on the entity.', $field), Response::HTTP_BAD_REQUEST
            );
        }

        $manager?->flush();

        return new Response($newValue ? '1' : '0');
    }

    private function updateProperty(object $entity, string $field, bool $value): bool
    {
        if (!$this->propertyAccessor->isWritable($entity, $field)) {
            return false;
        }

        $this->propertyAccessor->setValue($entity, $field, $value);

        return true;
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
