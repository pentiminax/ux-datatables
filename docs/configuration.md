# DataTables Configuration

Use the bundle configuration to define default DataTables options, template
attributes, and extensions. Create (or update) a `config/packages/datatables.yaml`
file like this:

```yaml
data_tables:
  options:
    language: en-GB
    layout:
      topStart: pageLength
      topEnd: search
      bottomStart: info
      bottomEnd: paging
    lengthMenu: [10, 25, 50]
    pageLength: 10
  template_parameters:
    class: 'table'
  extensions:
    buttons: [csv, excel, pdf, print]
    select:
      style: single
```

## Option reference

**language**: The default language locale used by DataTables. This should be one
of the supported DataTables JSON locales (e.g. `en-GB`, `fr-FR`). The bundle
translates it into the correct CDN URL.

**layout**: Controls where DataTables UI features are rendered. Each position
maps to a DataTables feature name. Use this to place `pageLength`, `search`,
`info`, `paging`, or `buttons` in the layout.

**lengthMenu**: An array of values representing the number of rows to display per page. In this example, users can choose from 10, 25, or 50 rows per page.

**pageLength**: The initial number of rows to display per page. In the example above, it is set to 10.

**template_parameters**: Attributes passed to the Twig `render_datatable` helper
by default. In the example, the `class` attribute is set to `table`.

- **class**: The CSS class to apply to the generated DataTables table.

**extensions**: Default extensions enabled for every table created by the
builder.

- **select**: Configuration for the Select extension.
    - **style**: Defines the selection style. Supported values are `single` or
      `multi`.

These defaults are passed to every `DataTableBuilderInterface::createDataTable()`
call and can still be overridden per table in PHP.
