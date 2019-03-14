<?php

namespace Drupal\bigcommerce\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Gets all Product Option Fields from BigCommerce API.
 *
 * @MigrateSource(
 *   id = "bigcommerce_product_option_field"
 * )
 */
class ProductOptionField extends ProductOption {

  /**
   * {@inheritdoc}
   */
  public function getYield(array $params) {
    $total_pages = 1;
    while ($params['page'] < $total_pages) {
      $params['page']++;
      $options = [];

      $response = $this->getSourceResponse($params);
      foreach ($response->getData() as $option) {
        $option_fields = $this->getOptionFields($option->getType());
        $option_name = $option->getName();

        // If no fields are required, skip this option.
        if (empty($option_fields) || in_array($option_name, $options)) {
          continue;
        }

        foreach ($option_fields as $field) {
          $field['attribute_name'] = $option_name;
          yield $field;
        }

        $options[] = $option_name;
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
    // If some fields requires special settings, process them.
    $import_type = $row->getSourceProperty('import_type');
    if ($row->hasSourceProperty($import_type . '_settings')) {
      $settings = $row->getSourceProperty($import_type . '_settings');
      $row->setDestinationProperty('settings', $settings);
    }
    return parent::prepareRow($row);
  }

}
