<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Export;

use Pentiminax\UX\DataTables\Contracts\ExporterInterface;
use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ExporterRegistry
{
    /** @var array<string, ExporterInterface> */
    private readonly array $exporters;

    /**
     * @param iterable<ExporterInterface> $exporters
     */
    public function __construct(iterable $exporters)
    {
        $indexed = [];
        foreach ($exporters as $exporter) {
            $indexed[$exporter->format()->value] = $exporter;
        }

        $this->exporters = $indexed;
    }

    /**
     * @throws BadRequestHttpException when no exporter can handle the format
     */
    public function get(ExportFormat $format): ExporterInterface
    {
        $exporter = $this->exporters[$format->value] ?? null;

        if (null === $exporter) {
            throw new BadRequestHttpException(\sprintf('No exporter is registered for the "%s" format.', $format->value));
        }

        if (!$exporter->isAvailable()) {
            throw new BadRequestHttpException(\sprintf('Server-side %s export requires openspout/openspout. Run "composer require openspout/openspout".', strtoupper($format->value)));
        }

        return $exporter;
    }
}
