<?php

namespace Pentiminax\UX\DataTables\Controller;

use Doctrine\ORM\EntityManagerInterface;
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
        private readonly ?ManagerRegistry $doctrine = null,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function __invoke(#[MapRequestPayload] AjaxEditRequestDto $payload): Response
    {
        if (null === $this->doctrine) {
            return $this->jsonError('Doctrine is required to update boolean values.', Response::HTTP_NOT_IMPLEMENTED);
        }

        $entityClass = $payload->entity;

        $manager = $this->doctrine->getManagerForClass($entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            return $this->jsonError('Entity manager not found for provided entity.', Response::HTTP_BAD_REQUEST);
        }

        $metadata = $manager->getClassMetadata($entityClass);
        $field    = $payload->field;

        if (!$metadata->hasField($field)) {
            return $this->jsonError(sprintf('Field "%s" does not exist on "%s".', $field, $entityClass), Response::HTTP_BAD_REQUEST);
        }

        $fieldType = strtolower((string) $metadata->getTypeOfField($field));
        if (!\in_array($fieldType, ['bool', 'boolean'], true)) {
            return $this->jsonError(sprintf('Field "%s" must be mapped as boolean.', $field), Response::HTTP_BAD_REQUEST);
        }

        /** @var ObjectRepository<object> $repository */
        $repository = $manager->getRepository($entityClass);
        $id         = $payload->id;
        $entity     = $repository->find($id);

        if (!\is_object($entity)) {
            return $this->jsonError('Entity not found.', Response::HTTP_NOT_FOUND);
        }

        $newValue = $payload->newValue;

        if (!$this->updateProperty($entity, $field, $newValue)) {
            return $this->jsonError(sprintf('Unable to write "%s" on the entity.', $field), Response::HTTP_BAD_REQUEST);
        }

        $manager->flush();

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
