# DataTables Configuration

To configure the default DataTables options, you can define them in the configuration file `config/packages/datatables.yaml` as shown below:

```yaml
data_tables:
  options:
    language: en-GB
    lengthMenu: [10, 25, 50]
    pageLength: 10
  template_parameters:
    class: 'table'
  extensions:
    buttons: [csv, excel, pdf, print]
    select:
      style: single
```

# Explanation of the options:

**language**: This section allows you to customize the language settings for DataTables. You can specify translations for various elements such as "processing", "lengthMenu", "zeroRecords", etc. In the example, the language is set to English.

**lengthMenu**: An array of values representing the number of rows to display per page. In this example, users can choose from 10, 25, or 50 rows per page.

**pageLength**: The initial number of rows to display per page. In the example above, it is set to 10.

**template_parameters**: An array of parameters to pass to the DataTables template. In this example, the class attribute is set to "table".

- **class**: The CSS class to apply to the generated DataTables table.

**extensions**: An array defining additional extensions for DataTables.

- **select**: Configuration for the select extension.
    - **style**: Defines the selection style. In this case, it is set to "single". Can be "single" or "multi".

These options allow you to customize the DataTables behavior directly from your configuration file.

