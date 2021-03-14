# Views Migration

This module provides views migrate from drupal 7 to drupal 8 or 9. 

## _Steps for Using Views Migration Module_
 - Download and install the migrate_plus module into your new Drupal 8 site
 - Config your drupal 7 database in Drupal 8 upgrade /upgrade page
 - check with drush migrate:status in your terminal
    ```sh
    drush migrate:status d7_views_migration
    ```
 - Import Drupal 7 views with 
    ```sh
    drush migrate:import d7_views_migration
    ```