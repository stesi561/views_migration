<?php

namespace Drupal\views_migration\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\source\BaseViewsMigration;

/**
 * Drupal 7 views source from database.
 *
 * @MigrateSource(
 *   id = "d7_views_migration",
 *   source_module = "views"
 * )
 */
class ViewsMigration extends BaseViewsMigration {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('views_view', 'vv')
      ->fields('vv', [
        'vid', 'name', 'description', 'tag', 'base_table', 'human_name', 'core',
]);
    $query->where('vv.vid = 134');
    return $query;
  }

  /**
   * ViewsMigration prepareRow.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration source ROW.
   */
  public function prepareRow(Row $row) {
    $this->row = $row;
    $this->view = $row->getSourceProperty('name');
    $vid = $row->getSourceProperty('vid');
    $base_table = $row->getSourceProperty('base_table');
    $base_table_plugin = $this->getViewBaseTableMigratePlugin($base_table);
    $base_table = $base_table_plugin->getNewBaseTable($base_table);
    $available_views_tables = array_keys($this->viewsData);

    try {
      if (!in_array($base_table, $available_views_tables)) {
        throw new MigrateSkipRowException('The views base table ' . $base_table . ' is not exist in your database.');
      }
    }
    catch (MigrateSkipRowException $e) {
      $skip = TRUE;
      $save_to_map = $e->getSaveToMap();
      if ($message = trim($e->getMessage())) {
        $this->idMap->saveMessage($row->getSourceIdValues(), $message, MigrationInterface::MESSAGE_INFORMATIONAL);
      }
      if ($save_to_map) {
        $this->idMap->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_IGNORED);
        $this->currentRow = NULL;
        $this->currentSourceIds = NULL;
      }
      return FALSE;
    }
    $query = $this->select('views_display', 'vd')
      ->fields('vd', [
        'id', 'display_title', 'display_plugin', 'display_options', 'position',
      ]);
    $query->condition('vid', $vid);
    $execute = $query->execute();
    $this->defaultRelationships = [];
    $this->defaultArguments = [];
    $display = [];
    $row->setSourceProperty('base_table', $base_table_plugin->getNewSourceBaseTable($base_table));
    $row->setSourceProperty('base_field', $base_table_plugin->getNewSourceBaseField($base_table));
    $entity_type = $base_table_plugin->getBaseTableEntityType($base_table);
    $source_displays = [];
    // Prepare displays for processing. Ensure the "default" display is the
    // first display processed so that its configuration can be used when
    // processing other displays.
    while ($result = $execute->fetchAssoc()) {
      if ($result['id'] === 'default') {
        array_unshift($source_displays, $result);
        continue;
      }
      $source_displays[] = $result;
    }
    // Prepare the options for all displays.
    foreach ($source_displays as $source_display) {
      $display_options = $source_display['display_options'];
      $id = $source_display['id'];
      $this->display = $id;
      $display_options = unserialize($display_options);
      $display[$id]['display_plugin'] = $source_display['display_plugin'];
      $display[$id]['id'] = $source_display['id'];
      $display[$id]['display_title'] = $source_display['display_title'];
      $display[$id]['position'] = $source_display['position'];
      $display[$id]['display_options'] = $display_options;
      if (isset($source_display['display_plugin'])) {
        $this->getViewsPluginMigratePlugin('display', $source_display['display_plugin'])->prepareDisplayOptions($display[$id]);
      }
      $display[$id]['display_options'] = $this->convertDisplayPlugins($display[$id]['display_options']);
      $display[$id]['display_options'] = $this->convertHandlerDisplayOptions($display[$id]['display_options'], $entity_type);

      // Debugging help figure out before and after.
      //file_put_contents('/tmp/views-migration-debug-before.log', var_export($display[$id]['display_options'], TRUE));
      //

      $display[$id]['display_options'] = $this->fixCiviCRMEntityRelationships($display[$id]['display_options']);

      // Debugging check what we did worked
      //foreach($display[$id]['display_options']['fields'] as $dok => $dov) {
      //  var_export(
      //    [
      //    $dok => $dov['relationship']
      //    ]);
      //}
      
      
      $display[$id]['display_options'] = $this->removeNonExistFields($display[$id]['display_options']);

      // Debugging after
      //file_put_contents('/tmp/views-migration-debug-after.log', var_export($display[$id]['display_options'], TRUE));

      $this->logBrokenHandlers($display[$id]['display_options']);
      $this->display = NULL;
    }
    $row->setSourceProperty('display', $display);
    $this->row = NULL;
    $this->view = NULL;
    return parent::prepareRow($row);
  }

  private function fixCiviCRMEntityRelationships(array $display_options) {
    $relationship_key_mapping = [];
    $field_key_mapping = [];
    foreach($display_options['relationships'] as $r_key => $relationship_options) {
      switch($r_key) {
      case 'relationship_id_a':
      case 'relationship_id_b':
        $direction = substr($r_key, -1);
        $new_relationship = $relationship_options;        
        $new_relationship['id'] = 'reverse__civicrm_relationship__contact_id_' . $direction;
        $new_relationship['field']  = 'reverse__civicrm_relationship__contact_id_' . $direction;
        $new_relationship['plugin_id'] = 'civicrm_entity_civicrm_relationship';        
        break;
      case 'contact_id_a_':
      case 'contact_id_b_':
        $direction = substr($r_key, -2, 1);
        $new_relationship = $relationship_options;
        $new_relationship['id'] = 'contact_id_' . $direction;
        $new_relationship['field']  = 'contact_id_' . $direction;
        $new_relationship['plugin_id'] = 'civicrm_entity_civicrm_relationship';        
        break;
      default:
        continue 2;
      }
      $relationship_key_mapping[$r_key] = $new_relationship['id'];
                                                         
      if (!empty($new_relationship['relationship']) && $new_relationship['relationship'] != 'none') {
        if (!empty($relationship_key_mapping[$new_relationship['relationship']])) {
          $new_relationship['relationship'] = $relationship_key_mapping[$new_relationship['relationship']];
        }
      }

      $display_options['relationships'][$new_relationship['id']] = $new_relationship;
      
      
    
      unset($display_options['relationships'][$r_key]);
    }
    // Update fields based on the relationship - do outside of the
    // loop so we only checking each field once.
    foreach($display_options['fields'] as $field_key => &$field_options) {
      if (empty($field_options['relationship'])) {
        continue;
      }
      
      $old_relationship_id = $field_options['relationship'];
      if (!empty($relationship_key_mapping[$old_relationship_id])) {
        $new_relationship_id = $relationship_key_mapping[$old_relationship_id];
        $field_options['relationship'] = $new_relationship_id;
      }
      
    }
    //var_export([
    //  'display_options new relationships' => $display_options['relationships'],
    //  'display options new fields' => $display_options['fields'],
    //]);
    return $display_options;
  }
}
