<?php

namespace Drupal\views_migration\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;
use Drupal\Core\Database\Database;
use Drupal\views\Views;

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
   * ViewsMigration get User Roles.
   */
  public function getUserRoles() {
    $query = $this->select('role', 'r')->fields('r', ['rid', 'name']);
    $results = $query->execute()->fetchAllAssoc('rid');
    $user_roles = [];
    $map = [
      1 => 'anonymous',
      2 => 'authenticated',
    ];
    foreach ($results as $rid => $role) {
      $user_roles[$rid] = isset($map[$rid]) ? $map[$rid] : $role['name'];
    }
    return $user_roles;
  }

  /**
   * ViewsMigration get Views Plugin List.
   */
  public function getPluginList() {
    $handlers = [
      'argument' => 'handler',
      'field' => 'handler',
      'filter' => 'handler',
      'relationship' => 'handler',
      'sort' => 'handler',
    ];
    $plugins = [
      'access' => 'plugin',
      'area' => 'handler',
      'argument_default' => 'plugin',
      'argument_validator' => 'plugin',
      'cache' => 'plugin',
      'display_extender' => 'plugin',
      'display' => 'plugin',
      'exposed_form' => 'plugin',
      'join' => 'plugin',
      'pager' => 'plugin',
      'query' => 'plugin',
      'row' => 'plugin',
      'style' => 'plugin',
      'wizard' => 'plugin',
    ];
    $pluginList = [];
    foreach ($plugins as $pluginName => $value) {
      $pluginNames = Views::fetchPluginNames($pluginName);
      $pluginList[$pluginName] = array_keys($pluginNames);
    }
    return $pluginList;
  }

  /**
   * ViewsMigration get Views formatter List.
   */
  public function getFormatterList() {
    $formatterManager = \Drupal::service('plugin.manager.field.formatter');
    $formats = $formatterManager->getOptions();
    $return_formats = [];
    $all_formats = [];
    foreach ($formats as $key => $value) {
      $return_formats['field_type'][$key] = array_keys($value);
      $all_formats = array_merge($all_formats, array_keys($value));
    }
    $return_formats['all_formats'] = $all_formats;
    return $return_formats;
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
    $pluginList = $this->getPluginList();
    while ($result = $execute->fetchAssoc()) {
      $display_options = $result['display_options'];
      $id = $result['id'];
      $display_options = unserialize($display_options);
      if (isset($result['display_plugin'])) {
        if (!in_array($result['display_plugin'], $pluginList['display'])) {
          $result['display_plugin'] = 'default';
        }
      }
      $display[$id]['display_plugin'] = $result['display_plugin'];
      $display[$id]['id'] = $result['id'];
      $display[$id]['display_title'] = $result['display_title'];
      $display[$id]['position'] = $result['position'];
      $display_options = $this->convertDisplayPlugins($display_options, $pluginList);
      $display_options = $this->convertFieldFormatters($display_options, $base_table_array, $entity_type, $entity_base_table);
      $display_options = $this->convertDisplayOptions($display_options, $base_table_array, $entity_type, $entity_base_table);
      $display[$id]['display_options'] = $display_options;
    }
    $row->setSourceProperty('display', $display);
    return parent::prepareRow($row);
  }

  /**
   * ViewsMigration convertDisplayPlugins.
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
  public function convertFieldFormatters(array $display_options, array $base_table_array, string $entity_type, string $bt) {
    $formatterList = $this->getFormatterList();
    if (is_array($display_options['fields'])) {
      foreach ($display_options['fields'] as $key => $field) {
        if (!in_array($field['type'], $formatterList['all_formats'])) {
          if (isset($base_table_array[$field['table']])) {
            $entity_detail = $base_table_array[$field['table']];
            $temp_entity_base_table = $entity_detail['data_table'];
            $temp_entity_type = $entity_detail['entity_id'];
            $temp_base_field = $entity_detail['entity_keys']['id'];
            $config = 'field.storage.' . $entity_type . '.' . $field['field'];
          }
          else {
            $config = 'field.storage.' . $entity_type . '.' . $field['field'];
          }
          $field_config = \Drupal::config($config);
          if (!is_null($field_config)) {
            $type = $field_config->get('type');
            $settings = $field_config->get('settings');
            if (isset($formatterList['field_type'][$type])) {
              $display_options['fields'][$key]['type'] = $formatterList['field_type'][$type][0];
              $display_options['fields'][$key]['settings'] = $settings;
            }
          }
          else {
            unset($display_options['fields']['key']['type']);
          }
        }
      }
    }
    return $display_options;
  }

  /**
   * ViewsMigration convertDisplayPlugins.
   *
   * @param array $display_options
   *   Views dispaly options.
   * @param array $pluginList
   *   Vies plugin list array.
   *   Views base table.
   */
  public function convertDisplayPlugins(array $display_options, array $pluginList) {
    if (isset($display_options['query']['type'])) {
      if (!in_array($display_options['query']['type'], $pluginList['query'])) {
        $display_options['query'] = [
          'type' => 'views_query',
          'options' => [],
        ];
      }
    }
    if (isset($display_options['access']['type'])) {
      if (!in_array($display_options['access']['type'], $pluginList['access'])) {
        $display_options['access'] = [
          'type' => 'none',
        ];
      }
      switch ($display_options['access']['type']) {
        case 'role':
          $roles = $this->getUserRoles();
          $role_approved = [];
          foreach ($display_options['access']['role'] as $key => $value) {
            $role_approved[$roles[$key]] = $roles[$key];
          }
          unset($display_options['access']['role']);
          $display_options['access']['options']['role'] = $role_approved;
          break;

        case 'perm':
          $permissions_map = [
            'use PHP for block visibility' => 'use PHP for settings',
            'administer site-wide contact form' => 'administer contact forms',
            'post comments without approval' => 'skip comment approval',
            'edit own blog entries' => 'edit own blog content',
            'edit any blog entry' => 'edit any blog content',
            'delete own blog entries' => 'delete own blog content',
            'delete any blog entry' => 'delete any blog content',
            'create forum topics' => 'create forum content',
            'delete any forum topic' => 'delete any forum content',
            'delete own forum topics' => 'delete own forum content',
            'edit any forum topic' => 'edit any forum content',
            'edit own forum topics' => 'edit own forum content',
          ];
          $perm = $display_options['access']['perm'];
          $perm = isset($permissions[$perm]) ? $permissions[$perm] : $perm;
          $display_options['access']['options']['perm'] = $perm;
          break;

        default:
          // code...
          break;
      }
    }
    if (isset($display_options['cache']['type'])) {
      if (!in_array($display_options['cache']['type'], $pluginList['cache'])) {
        $display_options['cache'] = [
          'type' => 'none',
        ];
      }
    }
    if (isset($display_options['exposed_form']['type'])) {
      if (!in_array($display_options['exposed_form']['type'], $pluginList['exposed_form'])) {
        $display_options['exposed_form'] = [
          'type' => 'basic',
        ];
      }
    }

    if (isset($display_options['pager']['type'])) {
      if (!in_array($display_options['pager']['type'], $pluginList['pager'])) {
        $display_options['pager'] = [
          'type' => 'none',
        ];
      }
    }
    if (isset($display_options['row_plugin'])) {
      if (!in_array($display_options['row_plugin'], $pluginList['row'])) {
        $display_options['row_plugin'] = 'fields';
      }
    }
    if (isset($display_options['style_plugin'])) {
      if (!in_array($display_options['style_plugin'], $pluginList['style'])) {
        $display_options['style_plugin'] = 'default';
      }
    }
    return $display_options;
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
    $entity_table_array = $this->entityTableArray();
    if (isset($display_options['relationships'])) {
      $display_options = $this->alterRelationshipsDisplayOptions($display_options, $base_table_array, $entity_type, $bt);
    }
    if (isset($display_options['sorts'])) {
      $display_options = $this->alterDisplayOptions($display_options, 'sorts', $base_table_array, $entity_table_array, $entity_type, $bt);
    }
    if (isset($display_options['filters'])) {
      $display_options = $this->alterFiltersDisplayOptions($display_options, 'filters', $base_table_array, $entity_table_array, $entity_type, $bt);
      $display_options = $this->alterFilters($display_options, 'filters', $base_table_array, $entity_table_array, $entity_type, $bt);
    }
    if (isset($display_options['fields'])) {
      $display_options = $this->alterDisplayOptions($display_options, 'fields', $base_table_array, $entity_table_array, $entity_type, $bt);
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
   * ViewsMigration baseTableArray.
   *
   * This function give the entities base table array.
   */
  public function entityTableArray() {
    $entity_table_array = [];
    $entity_list_def = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entity_list_def as $id => $entity_def) {
      $base_table = $entity_def->get('base_table');
      $data_table = $entity_def->get('data_table');
      $entity_keys = $entity_def->get('entity_keys');
      if (isset($data_table)) {
        $entity_table_array[$entity_keys['id']] = [
          'entity_id' => $id,
          'data_table' => $data_table,
          'entity_keys' => $entity_keys,
        ];
      }
    }
    return $entity_table_array;
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
   * @param array $entity_table_array
   *   Entities table array based on entity_ids.
   * @param string $entity_type
   *   Views base entity type.
   * @param string $bt
   *   Views base table.
   */
  public function alterDisplayOptions(array $display_options, string $option, array $base_table_array, array $entity_table_array, string $entity_type, string $bt) {
    $views_relationships = $display_options['relationships'];
    $db_schema = Database::getConnection()->schema();
    $fields = $display_options[$option];
    $types = [
      'yes-no', 'default', 'true-false', 'on-off', 'enabled-disabled',
      'boolean', 'unicode-yes-no', 'custom',
    ];
    $boolean_fields = [
      'status',
      'sticky',
    ];
    foreach ($fields as $key => $data) {
      if ((isset($data['type']) && in_array($data['field'], $boolean_fields)) || in_array($data['type'], $types)) {
        if (!in_array($data['type'], $types)) {
          $data['type'] = 'yes-no';
        }
        $fields[$key]['type'] = 'boolean';
        $fields[$key]['settings']['format'] = $data['type'];
        $fields[$key]['settings']['format_custom_true'] = $data['type_custom_true'];
        $fields[$key]['settings']['format_custom_false'] = $data['type_custom_false'];
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
        elseif (isset($entity_table_array[$data['table']])) {
          $entity_detail = $entity_table_array[$data['table']];
          $fields[$key]['table'] = $entity_detail['data_table'];
        }
        else {
          $result = mb_substr($fields[$key]['table'], 0, 10);
          if ($result == 'field_data') {
            $name = substr($fields[$key]['table'], 10);
          }
          else {
            $name = $fields[$key]['field'];
          }
          if (isset($fields[$key]['relationship'])) {
            $relationship_name = $fields[$key]['relationship'];
            $relationship = $views_relationships[$relationship_name];
            if ($relationship['relationship'] == 'none') {
              $relation_entity_type = $entity_type;
              if (isset($entity_table_array[$relationship['field']])) {
                $entity_detail = $entity_table_array[$relationship['field']];
                $relation_entity_type = $entity_detail['entity_id'];
              }
              else {
                $name_len = strlen($fields[$key]['field']);
                $entity_id_check = mb_substr($fields[$key]['field'], ($name_len - 4), 4);
                $field_name = $fields[$key]['field'];
                if ($entity_id_check == '_tid') {
                  $field_name = mb_substr($fields[$key]['field'], 0, ($name_len - 4));
                  $fields[$key]['field'] = $field_name;
                }
                $value_check = mb_substr($fields[$key]['field'], ($name_len - 6), 6);
                if ($value_check == '_value') {
                  $field_name = mb_substr($fields[$key]['field'], 0, ($name_len - 6));
                }
                $config = 'field.storage.' . $relation_entity_type . '.' . $relationship['field'];
                $field_config = \Drupal::config($config);
                if (!is_null($field_config)) {
                  $type = $field_config->get('type');
                  $settings = $field_config->get('settings');
                  if (isset($settings['target_type'])) {
                    $relation_entity_type = $settings['target_type'];
                    $fields[$key]['field'] = str_replace($relation_entity_type . '__', '', $fields[$key]['field']);
                  }
                }
              }

              $field_name = str_replace('field_data_', '', $relationship['table']);
              $config = 'field.storage.' . $relation_entity_type . '.' . $fields[$key]['field'];
              $field_config = \Drupal::config($config);
              if (!is_null($field_config)) {
                $type = $field_config->get('type');
                $settings = $field_config->get('settings');
                if (isset($settings['target_type'])) {
                  $table = $settings['target_type'] . '_' . $name;
                }
                else {
                  $table = $relation_entity_type . '_' . $name;
                }
              }
              else {
                unset($display_options['fields']['key']['type']);
              }
            }
            else {
              $table = $entity_type . '_' . $name;
            }
          }
          else {
            $table = $entity_type . '_' . $name;
          }
          $result = mb_substr($fields[$key]['table'], 0, 10);
          if ($result == 'field_data') {
            $name = substr($fields[$key]['table'], 10);
            $fields[$key]['table'] = $table;
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

  /**
   * ViewsMigration alterFiltersDisplayOptions.
   *
   * @param array $display_options
   *   Views dispaly options.
   * @param string $option
   *   View section option.
   * @param array $base_table_array
   *   Entities Base table array.
   * @param array $entity_table_array
   *   Entities table array based on entity_ids.
   * @param string $entity_type
   *   Views base entity type.
   * @param string $bt
   *   Views base table.
   */
  public function alterFiltersDisplayOptions(array $display_options, string $option, array $base_table_array, array $entity_table_array, string $entity_type, string $bt) {
    $views_relationships = $display_options['relationships'];
    $db_schema = Database::getConnection()->schema();
    $fields = $display_options[$option];
    $types = [
      'yes-no', 'default', 'true-false', 'on-off', 'enabled-disabled',
      'boolean', 'unicode-yes-no', 'custom',
    ];
    $boolean_fields = [
      'status',
      'sticky',
    ];
    foreach ($fields as $key => $data) {
      if (isset($data['table'])) {
        if (isset($base_table_array[$data['table']])) {
          $entity_detail = $base_table_array[$data['table']];
          $fields[$key]['table'] = $entity_detail['data_table'];
        }
        elseif (isset($entity_table_array[$data['table']])) {
          $entity_detail = $entity_table_array[$data['table']];
          $fields[$key]['table'] = $entity_detail['data_table'];
        }
        else {
          $result = mb_substr($fields[$key]['table'], 0, 10);
          if ($result == 'field_data') {
            $name = substr($fields[$key]['table'], 10);
          }
          else {
            $name = $fields[$key]['field'];
          }
          if (isset($fields[$key]['relationship'])) {
            $relationship_name = $fields[$key]['relationship'];
            if ($relationship_name == 'none') {
              $fields[$key] = $this->relationshipFieldChage($fields[$key], $entity_table_array, $entity_type, $fields, $key, $name);
            }
            else {
              $relationship = $views_relationships[$relationship_name];
              while ($relationship['relationship'] != 'none') {
                $relationship_name = $relationship['relationship'];
                $relationship = $views_relationships[$relationship_name];
              }
              $fields[$key] = $this->relationshipFieldChage($relationship, $entity_table_array, $entity_type, $fields, $key, $name);
            }
          }
          else {
            $table = $entity_type . '_' . $name;
          }
          $result = mb_substr($fields[$key]['table'], 0, 10);
          if ($result == 'field_data') {
            $name = substr($fields[$key]['table'], 10);
            $fields[$key]['table'] = $table;
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
  public function alterRelationshipsDisplayOptions(array $display_options, array $base_table_array, string $entity_type, string $bt) {
    $views_relationships = $display_options['relationships'];
    $db_schema = Database::getConnection()->schema();
    $relationships = $display_options['relationships'];
    $types = [
      'yes-no', 'default', 'true-false', 'on-off', 'enabled-disabled',
      'boolean', 'unicode-yes-no', 'custom',
    ];
    $boolean_relationships = [
      'status',
      'sticky',
    ];
    foreach ($relationships as $key => $data) {
      if ((isset($data['type']) && in_array($data['field'], $boolean_relationships)) || in_array($data['type'], $types)) {
        if (!in_array($data['type'], $types)) {
          $data['type'] = 'yes-no';
        }
        $relationships[$key]['type'] = 'boolean';
        $relationships[$key]['settings']['format'] = $data['type'];
        $relationships[$key]['settings']['format_custom_true'] = $data['type_custom_true'];
        $relationships[$key]['settings']['format_custom_false'] = $data['type_custom_false'];
      }
      if (isset($data['table'])) {
        $check_reverse = mb_substr($relationships[$key]['table'], 0, 8);
        if (isset($base_table_array[$data['table']])) {
          $entity_detail = $base_table_array[$data['table']];
          $relationships[$key]['table'] = $entity_detail['data_table'];
          $relationships[$key]['entity_type'] = $entity_detail['entity_id'];
        }
        else {
          $name = substr($relationships[$key]['table'], 11);
          if (isset($relationships[$key]['relationship'])) {
            $relationship_name = $relationships[$key]['relationship'];
            $relationship = $views_relationships[$relationship_name];
            if ($relationship['relationship'] == 'none') {
              $table = $entity_type . '__' . $name;
            }
            else {
              $table = $entity_type . '__' . $name;
            }
          }
          else {
            $table = $entity_type . '__' . $name;
          }
          $result = mb_substr($relationships[$key]['table'], 0, 10);
          if ($result == 'field_data') {
            $name = substr($relationships[$key]['table'], 11);
            $relationships[$key]['table'] = $table;
            $relationships[$key]['field'] = $name;
          }
          else {
            /* $relationships[$key]['field'] = $bt; */
          }
        }
        if (mb_substr($key, 0, 8) == 'reverse_') {
          $field_name = str_replace('reverse_', '', $relationships[$key]['field']);
          $field_name = str_replace('_' . $entity_type, '', $field_name);
          $relationships[$key]['field'] = 'reverse__' . $entity_type . '__' . $field_name;
          $relationships[$key]['admin_label'] = $relationships[$key]['label'];
          unset($relationships[$key]['label']);
          unset($relationships[$key]['ui_name']);
          $relationships[$key]['plugin_id'] = 'entity_reverse';
        }
      }
    }
    $display_options['relationships'] = $relationships;
    return $display_options;
  }

  /**
   * ViewsMigration alterFilters.
   *
   * @param array $display_options
   *   Views dispaly options.
   * @param string $option
   *   View section option.
   * @param array $base_table_array
   *   Entities Base table array.
   * @param array $entity_table_array
   *   Entities table array based on entity_ids.
   * @param string $entity_type
   *   Views base entity type.
   * @param string $bt
   *   Views base table.
   */
  public function alterFilters(array $display_options, string $option, array $base_table_array, array $entity_table_array, string $entity_type, string $bt) {
    $views_relationships = $display_options['relationships'];
    $db_schema = Database::getConnection()->schema();
    $fields = $display_options[$option];
    $types = [
      'yes-no', 'default', 'true-false', 'on-off', 'enabled-disabled',
      'boolean', 'unicode-yes-no', 'custom',
    ];
    $boolean_fields = [
      'status',
      'sticky',
    ];
    $roles = $this->getUserRoles();
    foreach ($fields as $key => $data) {
      if (isset($data['expose'])) {
        $role_approved = [];
        if (isset($data['expose']['remember_roles'])) {
          foreach ($data['expose']['remember_roles'] as $rid => $role_data) {
            $role_approved[$roles[$rid]] = $roles[$rid];
          }
        }
        $data['expose']['remember_roles'] = $role_approved;
      }
      switch ($data['table']) {
        case 'users_roles':
          $role_approved = [];
          foreach ($data['value'] as $rid => $role_data) {
            $role_approved[$roles[$rid]] = $roles[$rid];
          }
          $data['value'] = $role_approved;
          $data['plugin_id'] = 'user_roles';
          $data['entity_type'] = 'user';
          $data['entity_field'] = 'roles';
          $data['table'] = 'user__roles';
          $data['field'] = 'roles_target_id';
          break;

        case 'file_usage':
          $data['plugin_id'] = 'numeric';
          break;

        case 'views':
          if ($data['field'] == 'combine') {
            $data['plugin_id'] = 'combine';
          }
          break;

        default:
          // code...
          break;
      }
      if (isset($data['value']['type'])) {
        if ($data['value']['type'] == 'date') {
          $data['plugin_id'] = 'date';
        }
      }

      if (mb_substr($data['table'], 0, 5) == 'node_' && !isset($data['plugin_id'])) {
        $data['plugin_id'] = 'bundle';
        $data['entity_type'] = 'node';
        $data['entity_field'] = $data['field'];
      }

      if (mb_substr($data['table'], 0, 5) == 'user_' && !isset($data['plugin_id'])) {
        $data['plugin_id'] = 'bundle';
        $data['entity_type'] = 'user';
        $data['entity_field'] = $data['field'];
      }

      if (mb_substr($data['table'], 0, 5) == 'taxonomy_term_') {
        $data['plugin_id'] = 'bundle';
        $data['entity_type'] = 'taxonomy_term';
        $data['entity_field'] = $data['field'];
      }
      if (isset($data['vocabulary'])) {
        $data['plugin_id'] = 'taxonomy_index_tid';
        $data['vid'] = $data['vocabulary'];
        unset($data['vocabulary']);
      }
      $fields[$key] = $data;
    }
    $display_options[$option] = $fields;
    return $display_options;
  }

  /**
   * ViewsMigration relationshipFieldChage.
   *
   * @param array $relationship
   *   Views dispaly options.
   * @param array $entity_table_array
   *   Entities table array based on entity_ids.
   * @param string $entity_type
   *   Views base entity type.
   * @param string $fields
   *   View fields data.
   * @param string $key
   *   Views base table.
   * @param string $name
   *   Views field name.
   */
  public function relationshipFieldChage(array $relationship, array $entity_table_array, $entity_type, $fields, $key, $name) {
    $dont_changes = ['users_roles', 'file_usage', 'views'];
    if (in_array($fields[$key]['table'], $dont_changes)) {
      return $fields[$key];
    }

    $relation_entity_type = $entity_type;
    if (isset($entity_table_array[$relationship['field']])) {
      $entity_detail = $entity_table_array[$relationship['field']];
      $relation_entity_type = $entity_detail['entity_id'];
    }
    else {
      $name_len = strlen($fields[$key]['field']);
      $entity_id_check = mb_substr($fields[$key]['field'], ($name_len - 4), 4);
      $field_name = $fields[$key]['field'];
      if ($entity_id_check == '_tid') {
        $field_name = mb_substr($fields[$key]['field'], 0, ($name_len - 4));
        $fields[$key]['field'] = $field_name . '_target_id';
      }
      $value_check = mb_substr($fields[$key]['field'], ($name_len - 6), 6);
      if ($value_check == '_value') {
        $field_name = mb_substr($fields[$key]['field'], 0, ($name_len - 6));
      }
      $config = 'field.storage.' . $relation_entity_type . '.' . $relationship['field'];
      $field_config = \Drupal::config($config);
      if (!is_null($field_config)) {
        $type = $field_config->get('type');
        $settings = $field_config->get('settings');
        if (isset($settings['target_type'])) {
          $relation_entity_type = $settings['target_type'];
          $fields[$key]['field'] = str_replace($relation_entity_type . '__', '', $fields[$key]['field']);
        }
      }
    }

    $field_name = str_replace('field_data_', '', $relationship['table']);
    $config = 'field.storage.' . $relation_entity_type . '.' . $field_name;
    $field_config = \Drupal::config($config);
    if (!is_null($field_config)) {
      $type = $field_config->get('type');
      $settings = $field_config->get('settings');
      if (isset($settings['target_type'])) {
        if ($settings['target_type'] == 'taxonomy_term') {
          $table = $relation_entity_type . '_' . $name;
        }
        else {
          $table = $settings['target_type'] . '_' . $name;
        }
      }
      else {
        $table = $relation_entity_type . '_' . $name;
      }
    }
    else {
      unset($display_options['fields']['key']['type']);
    }
    $fields[$key]['table'] = $table;
    return $fields[$key];
  }
}