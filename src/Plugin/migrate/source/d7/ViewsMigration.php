<?php

namespace Drupal\views_migration\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\Condition;

/**
 * Drupal 7 views source from database.
 *
 * @MigrateSource(
 *   id = "d7_views_migration",
 *   source_module = "views"
 * )
 */
class ViewsMigration extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      "vid" => $this->t("vid"),
      "name" => $this->t("name"),
      "description" => $this->t("description"),
      "tag" => $this->t("tag"),
      "base_table" => $this->t("base_table"),
      "human_name" => $this->t("human_name"),
      "core" => $this->t("core"),
      "id" => $this->t("id"),
      "display_title" => $this->t("display_title"),
      "display_plugin" => $this->t("display_plugin"),
      "position" => $this->t("position"),
      "display_options" => $this->t("display_options"),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['vid']['type'] = 'integer';
    $ids['vid']['alias'] = 'vv';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('views_view', 'vv')
      ->fields('vv', [
        'vid', 'name', 'description', 'tag', 'base_table', 'human_name', 'core',
      ]);
    return $query;
  }

  /**
   * ViewsMigration prepareRow.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration source ROW.
   */
  public function prepareRow(Row $row) {
    $vid = $row->getSourceProperty('vid');
    $base_table = $row->getSourceProperty('base_table');
    $query = $this->select('views_display', 'vd')
      ->fields('vd', [
        'id', 'display_title', 'display_plugin', 'display_options', 'position',
      ]);
    $query->condition('vid', $vid);
    $execute = $query->execute();
    $display = [];
    $base_table_array = $this->baseTableArray();
    $entity_base_table = '';
    $entity_type = '';
    $base_field = NULL;
    if (isset($base_table_array[$base_table])) {
      $entity_detail = $base_table_array[$base_table];
      $entity_base_table = $entity_detail['data_table'];
      $entity_type = $entity_detail['entity_id'];
      $base_field = $entity_detail['entity_keys']['id'];
    }
    else {
      $entity_base_table = $base_table;
      $entity_type = 'node';
      $base_field = 'nid';
    }
    $row->setSourceProperty('base_table', $entity_base_table);
    $row->setSourceProperty('base_field', $base_field);
    while ($result = $execute->fetchAssoc()) {
      $display_options = $result['display_options'];
      $id = $result['id'];
      $display_options = unserialize($display_options);
      $display[$id]['display_plugin'] = $result['display_plugin'];
      $display[$id]['id'] = $result['id'];
      $display[$id]['display_title'] = $result['display_title'];
      $display[$id]['position'] = $result['position'];
      $display_options = $this->convertDisplayOptions($display_options, $base_table_array, $entity_type, $entity_base_table);
      $display[$id]['display_options'] = $display_options;
    }
    $row->setSourceProperty('display', $display);
    return parent::prepareRow($row);
  }

  /**
   * ViewsMigration convertDisplayOptions.
   *
   * @param array $display_options
   *   Views dispaly options.
   * @param array $base_table_array
   *   Entities Base table array.
   * @param string $entity_type
   *   Views base entity type.
   * @param string $bt
   *   Views base table.
   */
  public function convertDisplayOptions(array $display_options, array $base_table_array, string $entity_type, string $bt) {
    if (isset($display_options['relationships'])) {
      $display_options = $this->alterDisplayOptions($display_options, 'relationships', $base_table_array, $entity_type, $bt);
    }
    if (isset($display_options['sorts'])) {
      $display_options = $this->alterDisplayOptions($display_options, 'sorts', $base_table_array, $entity_type, $bt);
    }
    if (isset($display_options['filters'])) {
      $display_options = $this->alterDisplayOptions($display_options, 'filters', $base_table_array, $entity_type, $bt);
    }
    if (isset($display_options['fields'])) {
      $display_options = $this->alterDisplayOptions($display_options, 'fields', $base_table_array, $entity_type, $bt);
    }
    return $display_options;
  }

  /**
   * ViewsMigration baseTableArray.
   *
   * This function give the entities base table array.
   */
  public function baseTableArray() {
    $base_table_array = [];
    $entity_list_def = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entity_list_def as $id => $entity_def) {
      $base_table = $entity_def->get('base_table');
      $data_table = $entity_def->get('data_table');
      $entity_keys = $entity_def->get('entity_keys');
      $base_table_array[$base_table]['entity_id'] = $id;
      $base_table_array[$base_table]['data_table'] = $data_table;
      $base_table_array[$base_table]['entity_keys'] = $entity_keys;
    }
    return $base_table_array;
  }

  /**
   * ViewsMigration convertDisplayOptions.
   *
   * @param array $display_options
   *   Views dispaly options.
   * @param string $option
   *   View section option.
   * @param array $base_table_array
   *   Entities Base table array.
   * @param string $entity_type
   *   Views base entity type.
   * @param string $bt
   *   Views base table.
   */
  public function alterDisplayOptions(array $display_options, string $option, array $base_table_array, string $entity_type, string $bt) {
    $db_schema = Database::getConnection()->schema();
    $fields = $display_options[$option];
    foreach ($fields as $key => $data) {
      if (isset($data['type'])) {
        $types = [
          'yes-no', 'default', 'true-false', 'on-off', 'enabled-disabled',
          'boolean', 'unicode-yes-no', 'custom',
        ];
        if (in_array($data['type'], $types)) {
          $fields[$key]['type'] = 'boolean';
          $fields[$key]['settings']['format'] = $data['type'];
          $fields[$key]['settings']['format_custom_true'] = $data['type_custom_true'];
          $fields[$key]['settings']['format_custom_false'] = $data['type_custom_false'];
        }
      }
      if (isset($data['field'])) {
        $types = [
          'view_node', 'edit_node', 'delete_node', 'cancel_node', 'view_user', 'view_comment', 'edit_comment', 'delete_comment', 'approve_comment', 'replyto_comment',
        ];
        $table_map = [
          'views_entity_node' => 'node',
          'users' => 'users',
          'comment' => 'comment',
        ];
        if (in_array($data['field'], $types)) {
          $fields[$key]['table'] = $table_map[$data['table']];
        }
      }
      if (isset($data['table'])) {
        if (isset($base_table_array[$data['table']])) {
          $entity_detail = $base_table_array[$data['table']];
          $fields[$key]['table'] = $entity_detail['data_table'];
        }
        else{
          $result = mb_substr($fields[$key]['table'], 0, 10);
          if ($result == 'field_data') {
            $name = substr($fields[$key]['table'], 10);
            $table = $entity_type . '_' . $name;
            $fields[$key]['table'] = $table;
            /*
            if ($db_schema->fieldExists($table, $fields[$key]['field'])) {
              print_r("Exists");
            }
            else {
              $table_fields_suffix = ['_value', '_target_id'];
              foreach ($table_fields_suffix as $value) {
                $field = $data['field'] . $value;
                if($db_schema->fieldExists($table, $field)){
                  print_r("$field in $table exists\n");
                  $fields[$key]['field'] = $field;
                  break;
                }
              }
            }
            */
          }
          else {
            /* $fields[$key]['field'] = $bt; */
          }
        }
      }
    }
    $display_options[$option] = $fields;
    return $display_options;
  }

}
