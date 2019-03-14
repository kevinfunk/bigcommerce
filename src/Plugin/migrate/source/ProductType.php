<?php

namespace Drupal\bigcommerce\Plugin\migrate\source;

/**
 * Gets all Product Types.
 *
 * @MigrateSource(
 *   id = "bigcommerce_product_type"
 * )
 */
class ProductType extends BigCommerceSource {

  /**
   * {@inheritdoc}
   */
  public function getYield(array $params) {
    foreach ($this->getProductTypes() as $type) {
      yield $type;
    }
  }

  /**
   * Get all the Product Types.
   *
   * @return array
   *   The list of product Types.
   */
  protected function getProductTypes() {
    return [
      [
        'name' => 'physical',
        'label' => 'Physical product',
        // TODO: Change this for proper variation Type : 'physical'.
        'variation_type' => 'default',
      ],
      [
        'name' => 'digital',
        'label' => 'Downloadable product',
        // TODO: Change this for proper variation Type : 'digital'.
        'variation_type' => 'default',
      ],
    ];
  }

}
