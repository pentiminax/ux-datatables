<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class EditFormBuilder
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly ColumnToFormTypeMapper $mapper,
    ) {
    }

    /**
     * @param string[] $identifierFields Field names of the entity's primary key (auto-disabled)
     */
    public function buildForm(object $entity, array $columns, array $identifierFields = []): FormInterface
    {
        $builder = $this->formFactory->createBuilder(FormType::class, $entity);

        foreach ($columns as $column) {
            $mapped = $this->mapper->map($column);

            if (null === $mapped) {
                continue;
            }

            $name = $column['name'] ?? null;

            if (null === $name || '' === $name) {
                continue;
            }

            $options = $mapped['options'];

            if (\in_array($name, $identifierFields, true)) {
                $options['disabled'] = true;
            }

            $builder->add($name, $mapped['formType'], $options);
        }

        return $builder->getForm();
    }
}
