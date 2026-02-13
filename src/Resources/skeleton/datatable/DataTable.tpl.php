<?php echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

use <?php echo $entity_class_name; ?>;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
<?php foreach ($column_uses as $column_use) { ?>
use <?php echo $column_use; ?>;
<?php } ?>
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(<?php echo $entity_short_name; ?>::class)]
final class <?php echo $class_name; ?> extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
<?php if (\count($columns) > 0) { ?>
        return [
<?php foreach ($columns as $column) { ?>
            <?php echo $column['column_class_short']; ?>::new('<?php echo $column['name']; ?>', '<?php echo $column['label']; ?>'),
<?php } ?>
        ];
<?php } else { ?>
        // TODO: add columns that exist on your entity.
        return [
            TextColumn::new('id', 'ID'),
        ];
<?php } ?>
    }
}
