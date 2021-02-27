<?php

namespace Drupal\views_migration\Plugin\migrate\source\d7;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
        'vid','name', 'description', 'tag', 'base_table', 'human_name', 'core'
      ]);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $vid = $row->getSourceProperty('vid');
    $query = $this->select('views_display', 'vd')
      ->fields('vd', [
        'id','display_title', 'display_plugin', 'display_options', 'position'
      ]);
    $query->condition('vid', $vid);
    $execute = $query->execute();
    $display = [];
    while ($result=$execute->fetchAssoc()) {
      $display_options = $result['display_options'];
      $id = $result['id'];
      $display_options = unserialize($display_options);
      $display[$id]['display_plugin']= $result['display_plugin'];
      $display[$id]['id']= $result['id'];
      $display[$id]['display_title']= $result['display_title'];
      $display[$id]['position']= $result['position'];
      $display[$id]['display_options']= $display_options;
    }
    $row->setSourceProperty('display',$display);
    return parent::prepareRow($row);
  }
}
