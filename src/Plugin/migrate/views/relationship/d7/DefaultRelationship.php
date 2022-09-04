<?php

namespace Drupal\views_migration\Plugin\migrate\views\relationship\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Relationship plugin.
 *
 * This plugin is used to prepare the Views `relationship` display options for
 * migration when no other migrate plugin exists for the current relationship
 * plugin.
 *
 * @MigrateViewsRelationship(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultRelationship extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $base_entity_type = $this->infoProvider->getViewBaseEntityType();
    if (isset($handler_config['table'])) {
      if (isset($this->baseTableArray[$handler_config['table']])) {
        $entity_detail = $this->baseTableArray[$handler_config['table']];
        $handler_config['table'] = $entity_detail['data_table'];
        $handler_config['entity_type'] = $entity_detail['entity_id'];
      }
      if (mb_strpos($handler_config['id'], 'reverse_') === 0) {
        $field_name = str_replace([
          'reverse_',
          '_' . $base_entity_type
        ], '', $handler_config['field']);
        $handler_config['field'] = 'reverse__' . $base_entity_type . '__' . $field_name;
        $handler_config['admin_label'] = $handler_config['label'];
        unset($handler_config['label'], $handler_config['ui_name']);
        $handler_config['plugin_id'] = 'entity_reverse';
      }
    }
  }

}
