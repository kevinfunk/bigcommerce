<?php

namespace Drupal\bigcommerce_stock\API;

use BigCommerce\Api\v3\ApiClient;
use BigCommerce\Api\v3\ApiException;
use Drupal\bigcommerce\ClientFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;

/**
 * Wrapper for bigcommerce webhooks.
 *
 * @package Drupal\bigcommerce_stock\API
 */
class WebhookService implements WebhookServiceInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The bigcommerce API client.
   *
   * @var \BigCommerce\Api\v3\ApiClient
   */
  protected $apiClient;

  /**
   * Constructs a new WebhookService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  public function create(array $values) {
    $httpBody = [
      'scope' => $values['scope'],
      'destination' => $this->getDestination(),
      'is_active' => $values['is_active'],
      'headers' => $values['headers'],
    ];

    $_header_accept = $this->getApiClient()->selectHeaderAccept(['application/json']);
    if (!is_null($_header_accept)) {
      $headerParams['Accept'] = $_header_accept;
    }

    $headerParams['Content-Type'] = $this->getApiClient()->selectHeaderContentType(['application/json']);

    try {
      return $this->getApiClient()->callApi(
        '/hooks',
        'POST',
        [],
        $httpBody,
        $headerParams
      );
    }
    catch (ApiException $e) {
      $response_body = $e->getResponseBody();
      $this->logger->error($response_body->title);

      throw new \Exception($response_body->title, $e->getCode());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function createOrUpdate(array $values) {
    try {
      return $this->create($values);
    }
    catch (\Exception $e) {
      $message = $e->getMessage();
      if ($e->getCode() === 422 && $message === 'This hook already exists.') {
        $webhooks = $this->getAll();
        $webhook = NULL;
        foreach ($webhooks as $current_webhook) {
          // Identity the webhook by the scope. We can only register one webhook
          // per scope type.
          if ($current_webhook->scope === $values['scope']) {
            $webhook = $current_webhook;
          }
        }

        if ($webhook->headers->username === $values['headers']['username'] && $webhook->headers->password === $values['headers']['password']) {
          // Webhook with the same username password and scope is setup. Just
          // return the webhook.
          return $webhook;
        }
        else {
          try {
            return $this->update($webhook->id, $values);
          }
          catch (\Exception $e) {
            $message = $e->getMessage();
            $this->logger->error($message);

            throw new \Exception($message, $e->getCode());
          }
        }
      }

      throw new \Exception($message, $e->getCode());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function get($id) {
    try {
      list($response, $statusCode, $httpHeader) = $this->getApiClient()
        ->callApi(
          '/hooks/' . $id,
          'GET',
          [],
          [],
          []
        );

      return current($response);
    }
    catch (ApiException $e) {
      $response_body = $e->getResponseBody();
      $this->logger->error($response_body->title);

      throw new \Exception($response_body->title);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getAll() {
    try {
      list($response, $statusCode, $httpHeader) = $this->getApiClient()
        ->callApi('/hooks', 'GET', [], [], []);

      return $response;
    }
    catch (ApiException $e) {
      $response_body = $e->getResponseBody();
      $this->logger->error($response_body->title);

      throw new \Exception($response_body->title);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function update($id, array $values) {
    $httpBody = [
      'scope' => $values['scope'],
      'destination' => $this->getDestination(),
      'is_active' => $values['is_active'],
      'headers' => $values['headers'],
    ];

    $_header_accept = $this->getApiClient()->selectHeaderAccept(['application/json']);
    if (!is_null($_header_accept)) {
      $headerParams['Accept'] = $_header_accept;
    }

    $headerParams['Content-Type'] = $this->getApiClient()->selectHeaderContentType(['application/json']);

    try {
      list($response, $statusCode, $httpHeader) = $this->getApiClient()
        ->callApi(
          '/hooks/' . $id,
          'PUT',
          [],
          $httpBody,
          $headerParams
        );

      return $response;
    }
    catch (ApiException $e) {
      $response_body = $e->getResponseBody();
      $this->logger->error($response_body->title);

      throw new \Exception($response_body->title);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function delete($id) {
    try {
      return $this->getApiClient()->callApi(
        '/hooks/' . $id,
        'DELETE',
        [],
        [],
        []
      );
    }
    catch (ApiException $e) {
      $response_body = $e->getResponseBody();
      $this->logger->error($response_body->title);

      throw new \Exception($response_body->title, $e->getCode());
    }
  }

  /**
   * Returns the webhook controller destination.
   *
   * @return string
   *   The webhook controller destination.
   */
  protected function getDestination() {
    return Url::fromRoute('bigcommerce_stock.webhook_listener')->setAbsolute()->toString();
  }

  /**
   * Returns the API client.
   *
   * @return \BigCommerce\Api\v3\ApiClient
   *   The API client.
   */
  protected function getApiClient() {
    if ($this->apiClient) {
      return $this->apiClient;
    }

    $bigcommerce_config = $this->configFactory->get('bigcommerce.settings');
    $apiSettings = $bigcommerce_config->get('api');

    // We need the second version of the api.
    $apiSettings['path'] = substr($apiSettings['path'], 0, -2) . "2/";
    $this->apiClient = new ApiClient(ClientFactory::createApiConfiguration($apiSettings));

    return $this->apiClient;
  }

}
