<?php

namespace Drupal\Tests\bigcommerce\Functional;

use BigCommerce\Api\v3\Model\CatalogSummaryResponse;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the BigCommerce ClientFactory.
 *
 * This is a BrowserTestBase so we can use BigCommerce API stubbing.
 *
 * @group bigcommerce
 */
class ClientFactoryTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bigcommerce',
    'bigcommerce_test',
  ];

  /**
   * Tests.
   */
  public function testClientFactory() {

    try {
      $catalog_client = \Drupal::service('bigcommerce.catalog');
      $this->fail('Excepted runtime exception not thrown');
    }
    catch (\RuntimeException $e) {
      $this->assertEquals('BigCommerce API is not configured', $e->getMessage());
    }

    // Configure BigCommerce to use the stub.
    $config = $this->config('bigcommerce.settings');
    $config->set('api', [
      'path' => Url::fromUri('base://bigcommerce_stub/connection')->setAbsolute()->toString(),
      'access_token' => 'an access token',
      'client_id' => 'a client ID',
      'client_secret' => 'a client secret',
      'timeout' => 15,
    ]);
    $config->save();

    /** @var \BigCommerce\Api\v3\Api\CatalogApi $catalog_client */
    $catalog_client = \Drupal::service('bigcommerce.catalog');
    $this->assertInstanceOf(CatalogSummaryResponse::class, $catalog_client->catalogSummaryGet());
  }

}
