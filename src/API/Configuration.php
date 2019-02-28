<?php

namespace Drupal\bigcommerce\API;

use BigCommerce\Api\v3\Configuration as BigCommerceConfiguration;

class Configuration extends BigCommerceConfiguration {
  protected $clientId;
  protected $clientSecret;

  /**
   * @return string
   */
  public function getClientId() {
    return $this->clientId;
  }

  /**
   * @param string $clientId
   *
   * @return static
   */
  public function setClientId($clientId) {
    $this->clientId = $clientId;
    return $this;
  }

  /**
   * @return string
   */
  public function getClientSecret() {
    return $this->clientSecret;
  }

  /**
   * @param string $clientSecret
   *
   * @return static
   */
  public function setClientSecret($clientSecret) {
    $this->clientSecret = $clientSecret;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultHeaders() {
    return array_merge($this->defaultHeaders, [
      'X-Auth-Client' => $this->clientId,
      'X-Auth-Token'  => $this->accessToken,
    ]);
  }

}
