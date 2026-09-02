# Custom exporters (server-side export)

Server-side export (`Button::csv(serverSide: true)` / `Button::excel(serverSide: true)`, see `references/extensions.md`) streams every filtered row through an `ExporterInterface`. Replace the writer to change formatting rules (delimiter, locale, extra columns) — you cannot add a new export *format* without changing the bundle (`ExportFormat` is a closed enum: `csv`, `xlsx`).

## Contract (`src/Contracts/ExporterInterface.php`)

```php
interface ExporterInterface
{
    public function format(): ExportFormat;
    public function isAvailable(): bool;   // checked by ExporterRegistry before the response is built
    public function write(array $columns, iterable $rows): void; // called inside a StreamedResponse
}
```

- `$rows` is a generator over the whole filtered result — consume it once, never buffer it.
- Nothing recoverable may throw from `write()`: headers are already sent. Put anything checkable in `isAvailable()` so a missing dependency 400s cleanly instead of truncating the download.

## Extend `AbstractExporter` instead of implementing raw

`AbstractExporter` (`src/Export/AbstractExporter.php`) owns the shared OpenSpout pipeline — `write()` is `final`. Override only:

| Method | Purpose |
|---|---|
| `format()` | the `ExportFormat` case handled |
| `isAvailable()` | whether the writer library is installed |
| `createWriter(): WriterInterface` | the OpenSpout writer |
| `headings(array $columns)` | header labels (default: column titles) |
| `cellValue(ColumnInterface, array $row)` | one cell's scalar value |
| `configureSheet(WriterInterface, array)` | post-write sheet config (XLSX freeze/filter) |

Keep formula neutralization and markup stripping if overriding `cellValue()` — `AbstractExporter`'s default guards against spreadsheet formula injection and renders `TemplateColumn` HTML as plain text.

## Replace a writer

No autoconfiguration tag — exporters are explicit services passed to `ExporterRegistry`'s constructor. Redefine the bundle's service id:

```php
// config/services.php
$container->services()->set('datatables.export.exporter.csv', SemicolonCsvExporter::class)->private();
```

```php
final class SemicolonCsvExporter extends AbstractExporter
{
    public function format(): ExportFormat { return ExportFormat::CSV; }
    public function isAvailable(): bool { return class_exists(\OpenSpout\Writer\CSV\Writer::class); }
    protected function createWriter(): WriterInterface
    {
        $options = new \OpenSpout\Writer\CSV\Options();
        $options->FIELD_DELIMITER = ';';
        return new \OpenSpout\Writer\CSV\Writer($options);
    }
}
```

An exporter built on a library other than OpenSpout implements `ExporterInterface` directly instead of extending `AbstractExporter`.

## Notes

- Columns come from `filterExportable()` — same rule as client export (`setExportable(false)` / `#[Column(exportable: false)]` excluded). Template Twig, action voters, URL generation, CSRF do not run for exported rows.
- `projectPage()` batching during export — see `references/gotchas.md` and `references/defining-a-datatable.md`.
- Row streaming comes from `StreamingDataProviderInterface::iterateRows()` when the data provider implements it (both shipped providers do); otherwise falls back to `fetchData()->data` (materializes the whole set).
