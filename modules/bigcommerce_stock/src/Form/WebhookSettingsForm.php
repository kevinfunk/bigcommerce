<?php

namespace Drupal\bigcommerce_stock\Form;

use BigCommerce\Api\v3\ApiClient;
use BigCommerce\Api\v3\Api\CatalogApi;
use BigCommerce\Api\v3\ApiException;
use Drupal\bigcommerce\ClientFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class WebhookSettingsForm.
 *
 * @package Drupal\bigcommerce_stock\Form
 */
class WebhookSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bigcommerce_stock_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'bigcommerce_stock.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $bigcommerce_config = $this->config('bigcommerce.settings');

    try {
      // This creates the CatalogAPI manually in order to provide a connection
      // tester not dependent on configuration.
      $settings = $bigcommerce_config->get('api');
      $base_client = new ApiClient(ClientFactory::createApiConfiguration($settings));
      $catalog_client = new CatalogApi($base_client);
      $catalog_client->catalogSummaryGet();
    }
    catch (\Exception $e) {
      $this->messenger()->addError(
        $this->t(
          'There was an error connecting to the BigCommerce API. <a href=":bigcommerce_settings">Ensure that your BigCommerce credentials are valid.</a>',
          [':bigcommerce_settings' => Url::fromRoute('bigcommerce.settings')->toString()])
      );

      return $form;
    }

    $this->messenger()->addWarning(
      $this->t(
        'This module currently supports stock from variations only; product level stock is not supported.'
      )
    );

    $form['webhook'] = [
      '#type' => 'details',
      '#title' => $this->t('Register a stock webhook'),
      '#open' => TRUE,
    ];

    $config = $this->config('bigcommerce_stock.settings');

    if (!$config->get('username') && !$config->get('password')) {
      $form['webhook']['username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#default_value' => '',
        '#required' => TRUE,
      ];

      $form['webhook']['password'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Password'),
        '#default_value' => '',
        '#required' => TRUE,
      ];

      return $form;
    }

    $form['webhook']['#title'] = $this->t('Webhook settings');

    $form['webhook']['id'] = [
      '#type' => 'item',
      '#title' => $this->t('ID'),
      '#markup' => $config->get('id'),
    ];

    $form['webhook']['destination'] = [
      '#type' => 'item',
      '#title' => $this->t('Destination'),
      '#markup' => $config->get('destination'),
    ];

    $form['webhook']['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete webhook'),
      '#button_type' => 'secondary',
      '#submit' => [
        [$this, 'deleteWebhook'],
      ],
    ];

    return $form;
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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $destination = $this->getDestination();
    if (substr($destination, 0, 5) === "https") {
      return;
    }

    $this->messenger()->addError($this->t('You can only register a webhook from a HTTPS destination.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $bigcommerce_config = $this->config('bigcommerce.settings');
    $apiSettings = $bigcommerce_config->get('api');

    // We need the second version of the api.
    $apiSettings['path'] = substr($apiSettings['path'], 0, -2) . "2/";
    $apiClient = new ApiClient(ClientFactory::createApiConfiguration($apiSettings));

    $destination = $this->getDestination();

    $config = $this->configFactory()->getEditable('bigcommerce_stock.settings');
    $httpBody = [
      'scope' => 'store/sku/inventory/updated',
      'destination' => $destination,
      'is_active' => TRUE,
      'headers' => ['username' => $form_state->getValue('username'), 'password' => $form_state->getValue('password')],
    ];

    $_header_accept = $apiClient->selectHeaderAccept(['application/json']);
    if (!is_null($_header_accept)) {
      $headerParams['Accept'] = $_header_accept;
    }

    $headerParams['Content-Type'] = $apiClient->selectHeaderContentType(['application/json']);

    try {
      if (!$config->get('id')) {
        $apiClient->callApi(
          '/hooks',
          'POST',
          [],
          $httpBody,
          $headerParams
        );

        list($response, $statusCode, $httpHeader) = $apiClient->callApi('/hooks', 'GET', [], [], []);
        $webhook = current($response);

        $config->set('username', $form_state->getValue('username'))
          ->set('password', $form_state->getValue('password'))
          ->set('id', $webhook->id)
          ->set('destination', $webhook->destination)
          ->save();
      }
    }
    catch (ApiException $e) {
      $response_body = $e->getResponseBody();
      if ($response_body->status === 422 && $response_body->title === 'This hook already exists.') {
        list($response, $statusCode, $httpHeader) = $apiClient->callApi('/hooks', 'GET', [], [], []);
        $webhook = current($response);

        // Making sure we have the right webhook already configured.
        if ($webhook->headers->username === $form_state->getValue('username') && $webhook->headers->password === $form_state->getValue('password')) {
          $config->set('username', $form_state->getValue('username'))
            ->set('password', $form_state->getValue('password'))
            ->set('id', $webhook->id)
            ->set('destination', $webhook->destination)
            ->save();
        }

        return;
      }

      if ($response_body->status === 422) {
        $this->messenger()->addError($response_body->title);

        return;
      }

      $this->messenger()->addError($this->t(
        'There was an error setting up a webhook via the BigCommerce API ( <a href=":status_url">System Status</a> | <a href=":contact_url">Contact Support</a> ). Connection failed due to: %message',
        [
          ':status_url' => 'http://status.bigcommerce.com/',
          ':contact_url' => 'https://support.bigcommerce.com/contact',
          '%message' => $e->getMessage(),
        ]
      ));
    }
  }

  /**
   * Deletes the webhook.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function deleteWebhook(array &$form, FormStateInterface $form_state) {
    $bigcommerce_config = $this->config('bigcommerce.settings');
    $apiSettings = $bigcommerce_config->get('api');

    // We need the second version of the api.
    $apiSettings['path'] = substr($apiSettings['path'], 0, -2) . "2/";
    $apiClient = new ApiClient(ClientFactory::createApiConfiguration($apiSettings));
    $config = $this->configFactory()->getEditable('bigcommerce_stock.settings');

    try {
      $apiClient->callApi(
        '/hooks/' . $config->get('id'),
        'DELETE',
        [],
        [],
        []
      );

      $config->clear('username');
      $config->clear('password');
      $config->clear('id');
      $config->clear('destination');
      $config->save();
    }
    catch (ApiException $e) {
      $this->messenger()->addError($this->t('Failed to delete webhook'));
    }
  }

}
