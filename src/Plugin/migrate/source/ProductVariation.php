<?php

namespace Drupal\bigcommerce\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Gets all Product Option Fields from BigCommerce API.
 *
 * @MigrateSource(
 *   id = "bigcommerce_product_variation"
 * )
 */
class ProductVariation extends Product {

  /**
   * {@inheritdoc}
   */
  public function getYield(array $params) {
    $total_pages = 1;
    while ($params['page'] < $total_pages) {
      $params['page']++;

      // Load Default Store.
      $store = \Drupal::entityTypeManager()->getStorage('commerce_store')->loadDefault();

      $response = $this->getSourceResponse($params);
      foreach ($response->getData() as $product) {
        foreach ($product->getVariants() as $variant) {
          $variant = $variant->get();
          $variant['product_name'] = $product->getName();
          $variant['type'] = $product->getType();
          $variant['status'] = !$variant['purchasing_disabled'];
          $variant['currency_code'] = $store->getDefaultCurrencyCode();
          yield $variant;
        }
      }

      if ($params['page'] === 1) {
        $total_pages = $response->getMeta()->getPagination()->getTotalPages();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $migration = $this->migration;
    if (!isset($migration->orgProcess)) {
      $migration->orgProcess = $migration->get('process');
    }
    $process = $migration->orgProcess;

    // Process Product Attributes.
    $attributes = $row->getSourceProperty('option_values');
    if (!empty($attributes)) {
      foreach ($attributes as $attribute) {
        $id = $attribute->getId();
        // TODO: Modify Product Attribute migration to use fieldname rather than
        // TODO: using the field source Name.
        // Go through migrations to load up the initial attribute source ID.
        $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
        $attribute_value_migration = $migration_plugin_manager->createInstance('bigcommerce_product_attribute_value');
        $ids = $attribute_value_migration->getIdMap()->lookupDestinationId(['id' => $id]);
        $attribute_value = \Drupal::entityTypeManager()->getStorage('commerce_product_attribute_value')->load($ids[0]);
        $attribute_migration = $migration_plugin_manager->createInstance('bigcommerce_product_attribute');
        $attribute_source = $attribute_migration->getIdMap()->lookupSourceId(['id' => $attribute_value->getAttributeId()]);
        $variation_field_migration = $migration_plugin_manager->createInstance('bigcommerce_product_variation_type_field');
        $variation_field_destinations = $variation_field_migration->getIdMap()->lookupDestinationId(['source_name' => $attribute_value->getAttributeId()]);

        $row->setSourceProperty('attribute_' . $id . '_id', $id);
        $row->setSourceProperty('attribute_' . $id . '_name', $attribute_source['name']);

        $process[$variation_field_destinations[1]] = [
          [
            'plugin' => 'migration_lookup',
            'migration' => 'bigcommerce_product_attribute_value',
            'source_ids' => [
              'bigcommerce_product_attribute_value' => [
                'attribute_name' => 'attribute_' . $id . '_name',
                'id' => 'attribute_' . $id . '_id',
              ],
            ],
            'no_stub' => TRUE,
          ],
        ];
      }
    }

    $migration->set('process', $process);

    return parent::prepareRow($row);
  }

}
