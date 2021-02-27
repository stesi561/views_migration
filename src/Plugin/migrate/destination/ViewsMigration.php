<?php

namespace Drupal\views_migration\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityConfigBase;
use Drupal\migrate\Row;

/**
 * @MigrateDestination(
 *   id = "entity:view"
 * )
 */
class ViewsMigration extends EntityConfigBase {

  /**
     * {@inheritdoc}
     */
  public function import(Row $row, array $old_destination_id_values = []) {
    $entity_ids = parent::import($row, $old_destination_id_values);
    return $entity_ids;
  }

}
