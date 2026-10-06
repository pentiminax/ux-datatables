<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Export;

use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\StreamingDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\RequestInputBag;
use Pentiminax\UX\DataTables\Enum\ExportFormat;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExportService
{
    public function __construct(
        private readonly ExporterRegistry $exporters,
        private readonly ColumnResolver $columnResolver = new ColumnResolver(),
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Everything that can fail is resolved before the response is built: once StreamedResponse has
     * flushed its headers, a failure can no longer become an error page.
     */
    public function export(AbstractDataTable $table, Request $request): StreamedResponse
    {
        RequestInputBag::exposeBodyParametersOnQuery($request);

        $table->handleRequest($request);

        if (!$table->isRequestHandled()) {
            throw new BadRequestHttpException('Invalid DataTables request.');
        }

        $button = $this->resolveButton($table, $this->exportKeyFromRequest($request));
        $format = $button->getExportFormat() ?? ExportFormat::CSV;

        $exporter  = $this->exporters->get($format);
        $columns   = $this->exportableColumns($table);
        $rows      = $this->iterateRows($table);
        $formatter = new ExportValueFormatter($this->translator);

        if (!$button->usesRawValues()) {
            $this->translateLabels($columns);
            $this->applyExportHeadings($formatter, $columns);
            $rows = $this->formatRows($formatter, $columns, $rows);
        }

        $filename = ExportFilename::resolve($button->getFilename(), $table::class, $format);

        return new StreamedResponse(
            new ExportStream($exporter, $columns, $rows),
            200,
            [
                'Content-Type'        => $format->contentType(),
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    $filename,
                ),
            ],
        );
    }

    /**
     * @param list<ColumnInterface> $columns
     */
    private function translateLabels(array $columns): void
    {
        if (null === $this->translator) {
            return;
        }

        foreach ($columns as $column) {
            $title = $column->getTitle();
            if (null !== $title) {
                $column->setTitle($this->translator->trans($title));
            }

            if ($column instanceof ChoiceColumn) {
                $column->translateLabels($this->translator);
            }
        }
    }

    /**
     * @param list<ColumnInterface> $columns
     */
    private function applyExportHeadings(ExportValueFormatter $formatter, array $columns): void
    {
        foreach ($columns as $column) {
            $column->setTitle($formatter->heading($column, $column->getTitle() ?? $column->getName()));
        }
    }

    /**
     * @param list<ColumnInterface>          $columns
     * @param iterable<array<string, mixed>> $rows
     *
     * @return \Generator<array<string, mixed>>
     */
    private function formatRows(ExportValueFormatter $formatter, array $columns, iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            yield $formatter->formatRow($columns, $row);
        }
    }

    private function resolveButton(AbstractDataTable $table, ?string $exportKey): Button
    {
        $button = $table
            ->getConfiguredDataTable()
            ->getExtensionsCollection()
            ->getButtonsExtension()
            ?->findServerExportButton($exportKey);

        if (null === $button) {
            throw new BadRequestHttpException('No server-side export button is configured on this table.');
        }

        return $button;
    }

    /**
     * @return list<ColumnInterface>
     */
    private function exportableColumns(AbstractDataTable $table): array
    {
        $columns = $this->columnResolver->filterExportable(
            $this->columnResolver->filterStaticPermissions($table->getConfiguredDataTable()->getColumns(), $table::class),
        );

        if ([] === $columns) {
            throw new BadRequestHttpException('This table has no exportable column.');
        }

        return $columns;
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function iterateRows(AbstractDataTable $table): iterable
    {
        $request = $table->getRequest();
        if (null === $request) {
            throw new BadRequestHttpException('Invalid DataTables request.');
        }

        $provider = $table->getDataProvider();
        if (null === $provider) {
            throw new \LogicException(\sprintf('Table "%s" has no data provider, so it cannot be exported.', $table::class));
        }

        $request = $request->withoutPagination();

        return $provider instanceof StreamingDataProviderInterface
            ? $provider->iterateRows($request)
            : $provider->fetchData($request)->data;
    }

    private function exportKeyFromRequest(Request $request): ?string
    {
        $value = RequestInputBag::resolve($request)->get('exportKey');

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
