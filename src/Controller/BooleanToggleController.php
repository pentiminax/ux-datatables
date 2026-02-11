<?php

namespace Pentiminax\UX\DataTables\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BooleanToggleController
{
    public function __construct(
        private readonly ?ManagerRegistry $doctrine = null,
    ) {
    }

    public function __invoke(Request $request, ?string $id = null): JsonResponse
    {
        if (null === $this->doctrine) {
            return $this->jsonError('Doctrine is required to update boolean values.', Response::HTTP_NOT_IMPLEMENTED);
        }

        $payload = $this->decodePayload($request);

        $entityClass = ltrim((string) ($payload['entity'] ?? $request->query->get('entity') ?? ''), '\\');
        $field       = (string) ($payload['field'] ?? $request->query->get('fieldName') ?? $request->query->get('field') ?? '');
        $valueRaw    = $payload['value'] ?? $request->query->get('newValue') ?? $request->query->get('value');
        $id          = (string) ($payload['id'] ?? $request->query->get('id') ?? $id ?? '');

        if ('' === $entityClass || '' === $field || '' === $id) {
            return $this->jsonError('Missing required parameters: entity, id, field.', Response::HTTP_BAD_REQUEST);
        }

        $newValue = $this->normalizeBoolean($valueRaw);
        if (null === $newValue) {
            return $this->jsonError('Invalid boolean value.', Response::HTTP_BAD_REQUEST);
        }

        $manager = $this->doctrine->getManagerForClass($entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            return $this->jsonError('Entity manager not found for provided entity.', Response::HTTP_BAD_REQUEST);
        }

        $metadata = $manager->getClassMetadata($entityClass);
        if (!$metadata->hasField($field)) {
            return $this->jsonError(sprintf('Field "%s" does not exist on "%s".', $field, $entityClass), Response::HTTP_BAD_REQUEST);
        }

        $fieldType = strtolower((string) $metadata->getTypeOfField($field));
        if (!\in_array($fieldType, ['bool', 'boolean'], true)) {
            return $this->jsonError(sprintf('Field "%s" must be mapped as boolean.', $field), Response::HTTP_BAD_REQUEST);
        }

        /** @var ObjectRepository<object> $repository */
        $repository = $manager->getRepository($entityClass);
        $entity     = $repository->find($id);
        if (!\is_object($entity)) {
            return $this->jsonError('Entity not found.', Response::HTTP_NOT_FOUND);
        }

        if (!$this->writeBooleanValue($entity, $field, $newValue)) {
            return $this->jsonError(sprintf('Unable to write "%s" on the entity.', $field), Response::HTTP_BAD_REQUEST);
        }

        $manager->flush();

        return new JsonResponse([
            'success' => true,
            'id'      => $id,
            'entity'  => $entityClass,
            'field'   => $field,
            'value'   => $newValue,
        ]);
    }

    private function decodePayload(Request $request): array
    {
        $content = $request->getContent();
        if ('' === trim($content)) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }

    private function normalizeBoolean(mixed $rawValue): ?bool
    {
        if (null === $rawValue || '' === $rawValue) {
            return null;
        }

        if (\is_bool($rawValue)) {
            return $rawValue;
        }

        if (\is_int($rawValue)) {
            return 0 !== $rawValue;
        }

        if (\is_string($rawValue)) {
            $normalized = trim($rawValue);
            if ('' === $normalized) {
                return null;
            }

            return filter_var($normalized, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);
        }

        return null;
    }

    private function writeBooleanValue(object $entity, string $field, bool $value): bool
    {
        $accessor = $this->buildAccessorSuffix($field);
        $setter   = sprintf('set%s', $accessor);

        if (\is_callable([$entity, $setter])) {
            $entity->$setter($value);

            return true;
        }

        if (!property_exists($entity, $field)) {
            return false;
        }

        $reflection = new \ReflectionObject($entity);
        if (!$reflection->hasProperty($field)) {
            return false;
        }

        $property = $reflection->getProperty($field);
        if (!$property->isPublic()) {
            $property->setAccessible(true);
        }
        $property->setValue($entity, $value);

        return true;
    }

    private function buildAccessorSuffix(string $property): string
    {
        if (str_contains($property, '_') || str_contains($property, '-')) {
            $property = str_replace(['-', '_'], ' ', $property);
            $property = str_replace(' ', '', ucwords($property));
        }

        return ucfirst($property);
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
