<?php

namespace Drupal\bigcommerce\Form;

use BigCommerce\Api\v3\Api\CatalogApi;
use BigCommerce\Api\v3\ApiClient;
use Drupal\bigcommerce\ClientFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bigcommerce_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'bigcommerce.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bigcommerce.settings');

    $form['connection_status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Connection status'),
      '#access' => FALSE,
    ];
    $form['connection_status']['message'] = [
      '#markup' => $this->t('Connected successfully.'),
      '#type' => 'item',
      '#wrapper_attributes' => [
        'class' => ['messages', 'messages--status'],
      ],
    ];

    $form['api_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Credentials'),
      '#tree' => TRUE,
    ];

    $form['api_settings']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Path'),
      '#description' => $this->t('The API Path of your BigCommerce store.'),
      '#default_value' => $config->get('api.path'),
      '#placeholder' => 'https://api.bigcommerce.com/stores/STORE_ID/v3/',
      '#required' => TRUE,
    ];

    // Used with the client id to make API calls.
    $form['api_settings']['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('The API Access Token from BigCommerce.'),
      '#default_value' => $config->get('api.access_token'),
      '#required' => TRUE,
    ];

    // Used with the access token to make API calls.
    $form['api_settings']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('The API Client ID from BigCommerce.'),
      '#default_value' => $config->get('api.client_id'),
      '#required' => TRUE,
    ];

    // @TODO Where is this used?
    $form['api_settings']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('The API Client ID from BigCommerce.'),
      '#default_value' => $config->get('api.client_secret'),
    ];

    // Test the connection if we have some details. This is not done in
    // validation so that this configuration page acts as a connection status
    // page too.
    if ($config->get('api.path')) {
      $form['connection_status']['#access'] = TRUE;
      $failed_message = $this->testConnection($config->get('api'));
      if ($failed_message) {
        $form['connection_status']['message']['#markup'] = $failed_message;
        $form['connection_status']['message']['#wrapper_attributes']['class'] = ['messages', 'messages--error'];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable('bigcommerce.settings')
      ->set('api.path', $form_state->getValue(['api_settings', 'path']))
      ->set('api.access_token', $form_state->getValue(['api_settings', 'access_token']))
      ->set('api.client_id', $form_state->getValue(['api_settings', 'client_id']))
      ->set('api.client_secret', $form_state->getValue(['api_settings', 'client_secret']))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Tests the API connection configuration.
   *
   * @param array $settings
   *   An array based on bigcommerce.settings:api.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   Returns a TranslatableMarkup with the connection error or NULL if there
   *   is no error.
   */
  protected function testConnection(array $settings) {
    try {
      $base_client = new ApiClient(ClientFactory::createApiConfiguration($settings));
      $catalog_client = new CatalogApi($base_client);
      $response = $catalog_client->catalogSummaryGet();
    }
    catch (\Exception $e) {
      return $this->t(
        'There was an error connecting to the BigCommerce API ( <a href=":status_url">System Status</a> | <a href=":contact_url">Contact Support</a> ). Connection failed due to: %message',
        [
          ':status_url' => 'http://status.bigcommerce.com/',
          ':contact_url' => 'https://support.bigcommerce.com/contact',
          '%message' => $e->getMessage(),
        ]
      );
    }

    return NULL;
  }

}
