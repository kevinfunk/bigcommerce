<?php

namespace Drupal\bigcommerce;

use BigCommerce\Api\v3\Api\CatalogApi;
use BigCommerce\Api\v3\ApiClient;
use Drupal\bigcommerce\API\Configuration;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Create BigCommerce clients
 */
class ClientFactory {

  /**
   * @var \Drupal\bigcommerce\API\Configuration
   */
  protected $apiConfig;

  /**
   * @var \BigCommerce\Api\v3\ApiClient
   */
  protected $baseClient;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * ClientFactory constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * @return \BigCommerce\Api\v3\Api\CatalogApi
   */
  public function getCatalog() {
    return new CatalogApi($this->getBaseClient());
  }

  /**
   * @return \BigCommerce\Api\v3\ApiClient
   */
  protected function getBaseClient() {
    if (!$this->baseClient) {
      $this->baseClient = new ApiClient($this->getConfiguration());
    }
    return $this->baseClient;
  }

  /**
   * @return \Drupal\bigcommerce\API\Configuration
   */
  protected function getConfiguration() {
    if (!$this->apiConfig) {
      $config = $this->configFactory->get('bigcommerce.settings');
      if (!$config->get('api.path')) {
        throw new \RuntimeException('BigCommerce API is not configured');
      }
      $this->apiConfig = static::createApiConfiguration($config->get('api'));
    }
    return $this->apiConfig;
  }

  /**
   * Creates a BigCommerce configuration object based on an array of settings.
   *
   * @param array $settings
   *   An array of BigCommerce API settings.
   *
   * @return \Drupal\bigcommerce\API\Configuration
   */
  public static function createApiConfiguration(array $settings) {
    $api_config = new Configuration();
    $api_config
      ->setHost(rtrim($settings['path'], '/\\'))
      ->setClientId($settings['client_id'])
      ->setAccessToken($settings['access_token'])
      ->setClientSecret($settings['client_secret'])
      ->setCurlTimeout($settings['timeout'] ?? 15);
    // Supporting testing the API with a stub.
    if ($test_prefix = drupal_valid_test_ua()) {
      $api_config->setUserAgent(drupal_generate_test_ua($test_prefix));
    }
    return $api_config;
  }

}
