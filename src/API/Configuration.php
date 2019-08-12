<?php

namespace Drupal\bigcommerce\API;

use BigCommerce\Api\v3\Configuration as BigCommerceConfiguration;

/**
 * Extends the BigCommerce configuration class to store client ID and secret.
 */
class Configuration extends BigCommerceConfiguration {
  protected $clientId;
  protected $clientSecret;
  protected $drupalVersion;
  protected $pluginVersion;

  /**
   * Gets the BigCommerce API client id, used for API calls.
   *
   * @return string
   *   BigCommerce API client id.
   */
  public function getClientId() {
    return $this->clientId;
  }

  /**
   * Sets the BigCommerce API client id, used for API calls.
   *
   * @param string $clientId
   *   BigCommerce API Client ID, provided in BigCommerce admin section.
   *
   * @return static
   */
  public function setClientId($clientId) {
    $this->clientId = $clientId;
    return $this;
  }

  /**
   * Gets the BigCommerce API client secret, used for App calls.
   *
   * @return string
   *   BigCommerce API client secret.
   */
  public function getClientSecret() {
    return $this->clientSecret;
  }

  /**
   * Sets the BigCommerce API client secret, used for App calls.
   *
   * @param string $clientSecret
   *   BigCommerce API Client Secret, provided in BigCommerce admin section.
   *
   * @return static
   */
  public function setClientSecret($clientSecret) {
    $this->clientSecret = $clientSecret;
    return $this;
  }

  /**
   * Sets the Drupal version.
   *
   * @param string $version
   *   Takes a string of the format 'x.y.z', normally populated with
   *   /Drupal:Version.
   *
   * @return $this
   */
  public function setDrupalVersion($version) {
    $this->drupalVersion = $version;
    return $this;
  }

  /**
   * Sets the BigCommerce module version.
   *
   * @param string $version
   *   Takes a string of the format '8.x-1.y'.
   *
   * @return $this
   */
  public function setPluginVersion($version) {
    $this->pluginVersion = $version;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultHeaders() {
    return array_merge($this->defaultHeaders, [
      'X-Auth-Client' => $this->clientId,
      'X-Auth-Token'  => $this->accessToken,
      'X-Client-Type' => 'Drupal',
      'X-Client-Version' => $this->drupalVersion,
      'X-Plugin-Version' => $this->pluginVersion,
    ]);
  }

}
