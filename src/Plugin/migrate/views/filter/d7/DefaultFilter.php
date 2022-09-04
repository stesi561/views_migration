<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Filter plugin.
 *
 * This plugin is used to prepare the Views `filter` display options for
 * migration when no other migrate plugin exists for the current filter plugin.
 *
 * @MigrateViewsFilter(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultFilter extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['table'])) {
      $handler_config['table'] = $this->getViewsHandlerTableMigratePlugin($handler_config['table'])->getNewTableValue($this->infoProvider);
    }
    $this->alterEntityIdField($handler_config);
    $this->alterExposeSettings($handler_config);
    $this->alterVocabularySettings($handler_config);
    $this->configurePluginId($handler_config, 'filter');
  }

  /**
   * Alter the Filter Handler's "expose" settings.
   *
   * @param array $handler_config
   *   The Filter Handler's configuration to alter.
   */
  protected function alterExposeSettings(array &$handler_config) {
    if (!isset($handler_config['expose'])) {
      return;
    }
    $role_approved = [];
    if (isset($handler_config['expose']['remember_roles'])) {
      // Update User Roles to their new machine names.
      foreach ($handler_config['expose']['remember_roles'] as $rid => $role_data) {
        $role_approved[$this->userRoles[$rid]] = $this->userRoles[$rid];
      }
    }
    $handler_config['expose']['remember_roles'] = $role_approved;
  }

  /**
   * Alter the Filter Handler's "vocabulary" settings.
   *
   * @param array $handler_config
   *   The Filter Handler's configuration to alter.
   */
  private function alterVocabularySettings(array &$handler_config) {
    if (!isset($handler_config['vocabulary'])) {
      return;
    }
    $handler_config['plugin_id'] = 'taxonomy_index_tid';
    $handler_config['vid'] = $handler_config['vocabulary'];
    // Ensure an empty 'value' setting is an empty array.
    if (array_key_exists('value', $handler_config) && $handler_config['value'] === "") {
      $handler_config['value'] = [];
    }
    unset($handler_config['vocabulary']);
  }

}
