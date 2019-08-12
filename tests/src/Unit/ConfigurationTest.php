<?php

namespace Drupal\Tests\bigcommerce\Unit;

use Drupal\bigcommerce\API\Configuration;
use Drupal\Tests\UnitTestCase;

/**
 * Test the configuration set and get.
 *
 * @group bigcommerce
 *
 * @coversDefaultClass \Drupal\bigcommerce\API\Configuration
 */
class ConfigurationTest extends UnitTestCase {

  /**
   * @covers ::getDefaultHeaders
   */
  public function testDefaultHeaders() {
    $config = new Configuration();
    $config
      ->setClientId('client_id')
      ->setAccessToken('access')
      ->setDrupalVersion('8.x.y')
      ->setPluginVersion('8.x-1.y');

    $expected = [
      'X-Auth-Client' => 'client_id',
      'X-Auth-Token' => 'access',
      'X-Client-Type' => 'Drupal',
      'X-Client-Version' => '8.x.y',
      'X-Plugin-Version' => '8.x-1.y',
    ];
    $this->assertSame($expected, $config->getDefaultHeaders());
  }

}
