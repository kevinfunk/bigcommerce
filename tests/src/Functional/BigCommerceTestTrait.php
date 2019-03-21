<?php

namespace Drupal\Tests\bigcommerce\Functional;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Enables BigCommerce tests to run syncs.
 *
 * Tests that use this should implement \Drupal\migrate\MigrateMessageInterface.
 */
trait BigCommerceTestTrait {

  /**
   * Executes a set of migrations in dependency order.
   *
   * @param string|string[] $ids
   *   Array of migration IDs, in any order or a single ID. If this is empty it
   *   will execute all BigCommerce migrations.
   */
  protected function executeMigrations($ids = []) {
    // Keep track of all migrations run during this command so the same
    // migration is not run multiple times.
    static $executed_migrations = [];

    $manager = $this->container->get('plugin.manager.migration');
    $ids = (array) $ids;
    if (empty($ids)) {
      $executed_migrations = [];
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      foreach ($manager->createInstances([]) as $migration) {
        if (in_array('BigCommerce', $migration->getMigrationTags())) {
          $ids[] = $migration->id();
        }
      }
    }
    array_walk($ids, function ($id) use ($manager, &$executed_migrations) {
      // This is possibly a base plugin ID and we want to run all derivatives.
      $instances = $manager->createInstances($id);
      array_walk($instances, function (MigrationInterface $migration) use (&$executed_migrations) {
        $required_migrations = $migration->get('requirements');
        $required_migrations = array_filter($required_migrations, function ($value) use (&$executed_migrations) {
          return !isset($executed_migrations[$value]);
        });
        if (!empty($required_migrations)) {
          $this->executeMigrations($required_migrations);
        }
        (new MigrateExecutable($migration, $this))->import();
        $executed_migrations += [$migration->getPluginId() => $migration->getPluginId()];
      });
    });
  }

  /**
   * Implements \Drupal\migrate\MigrateMessageInterface::display().
   */
  public function display($message, $type = 'status') {
    // Do nothing.
  }

}
