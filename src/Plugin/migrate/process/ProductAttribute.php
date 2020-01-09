<?php

namespace Drupal\bigcommerce\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Finds the attribute values and stores in a correctly named row destination.
 *
 * @MigrateProcessPlugin(
 *   id = "bigcommerce_product_attribute"
 * )
 */
class ProductAttribute extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The migration lookup process plugin.
   *
   * @var \Drupal\migrate\Plugin\migrate\process\MigrationLookup
   */
  protected $migrationLookup;

  /**
   * Constructs a ProductAttribute object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The Migration Plugin Manager Interface.
   * @param \Drupal\migrate\Plugin\migrate\process\MigrationLookup $migration_lookup
   *   The Migration lookup process plugin.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationPluginManagerInterface $migration_plugin_manager, MigrationLookup $migration_lookup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->migrationLookup = $migration_lookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migration'),
      MigrationLookup::create($container, $configuration, $plugin_id, $plugin_definition, $migration)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Process Product Attributes.
    $option_values_ids = $row->getSourceProperty('option_values_ids');
    foreach ($option_values_ids as $option_values_id) {
      // TODO: Modify Product Attribute migration to use fieldname rather than
      // TODO: using the field source Name.
      $attribute_value_migration = $this->migrationPluginManager->createInstance('bigcommerce_product_attribute_value');
      $attribute_id = $attribute_value_migration->getIdMap()
        ->lookupDestinationId(['id' => $option_values_id]);
      if ($attribute_id) {
        $attribute_value = \Drupal::entityTypeManager()
          ->getStorage('commerce_product_attribute_value')
          ->load($attribute_id[0]);

        if ($attribute_value) {
          // Build the attribute field name.
          $variation_field_name = 'attribute_' . $attribute_value->getAttributeId();
          $new_value = $this->migrationLookup->transform($option_values_id, $migrate_executable, $row, $destination_property);
          $row->setDestinationProperty($variation_field_name, $new_value);
        }
      }
    }
  }

}
