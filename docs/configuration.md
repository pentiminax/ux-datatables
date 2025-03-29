# DataTables Configuration

To configure the default DataTables options, you can define them in the configuration file `config/packages/datatables.yaml` as shown below:

```yaml
data_tables:
  options:
    lengthMenu: [10, 25, 50]
    pageLength: 10
  template_parameters:
    class: 'table table-bordered'
```
# Explanation of the options:
**lengthMenu**: An array of values representing the number of rows to display per page. In this example, users can choose from 10, 25, or 50 rows per page.

**pageLength**: The initial number of rows to display per page. In the example above, it is set to 10.

**template_parameters**: An array of parameters to pass to the DataTables template. In this example, the class attribute is set to "table table-bordered".

- **class**: The CSS class to apply to the generated DataTables table.

These options allow you to customize the DataTables behavior directly from your configuration file.