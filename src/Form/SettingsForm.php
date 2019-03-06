<?php

namespace Drupal\bigcommerce\Form;

use BigCommerce\Api\v3\ApiClient;
use BigCommerce\Api\v3\Api\CatalogApi;
use BigCommerce\Api\v3\Api\ChannelsApi;
use BigCommerce\Api\v3\Api\SitesApi;
use BigCommerce\Api\v3\ApiException;
use BigCommerce\Api\v3\Model\CreateChannelRequest;
use BigCommerce\Api\v3\Model\SiteCreateRequest;
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

    $form['channel'] = [
      '#type' => 'details',
      '#title' => $this->t('Channel Settings'),
      '#description' => $this->t('These settings are generated automatically to link your site with BigCommerce'),
      '#open' => TRUE,
    ];

    $form['channel']['message'] = [
      '#type' => 'container',
      '#access' => FALSE,
      '#wrapper_attributes' => [
        'class' => ['messages', 'messages--status'],
      ],
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
      else {
        if (!$config->get('channel.id')) {
          $failed_message = $this->setupChannel($config->get('api'));
          if ($failed_message) {
            $form['channel']['message']['#access'] = TRUE;
            $form['channel']['message']['channel'] = [
              '#type' => 'item',
              '#markup' => $failed_message,
              '#wrapper_attributes' => [
                'class' => ['messages', 'messages--error'],
              ],
            ];
          }
        }

        if ($config->get('channel.id') && !$config->get('channel.site_id')) {
          $failed_message = $this->setupSite($config->get('api'));
          if ($failed_message) {
            $form['channel']['message']['#access'] = TRUE;
            $form['channel']['message']['site'] = [
              '#type' => 'item',
              '#markup' => $failed_message,
              '#wrapper_attributes' => [
                'class' => ['messages', 'messages--error'],
              ],
            ];
          }
        }
      }
    }

    $has_channel_id = mb_strlen($config->get('channel.id')) > 0;
    $has_site_id = mb_strlen($config->get('channel.site_id')) > 0;
    $form['channel']['no_channel'] = [
      '#markup' => $this->t('No channel is currently configured, once you provide valid API credentials this should configure automatically.'),
      '#access' => !$has_channel_id,
    ];
    $form['channel']['channel_id'] = [
      '#type' => 'item',
      '#title' => $this->t('Channel ID'),
      '#description' => $this->t('Channel ID from BigCommerce, used to identify 3rd part sales channels like Drupal or Amazon.'),
      '#markup' => $config->get('channel.id'),
      '#access' => $has_channel_id,
    ];

    $form['channel']['channel_name'] = [
      '#type' => 'item',
      '#title' => $this->t('Channel Name'),
      '#description' => $this->t('Channel Name from BigCommerce, user friendly tag used to identify 3rd party sales channels like Drupal or Amazon.'),
      '#markup' => $config->get('channel.name'),
      '#access' => $has_channel_id,
    ];

    $form['channel']['no_site'] = [
      '#markup' => $this->t('No BigCommerce site is currently configured, once you provide valid API credentials this should configure automatically.'),
      '#access' => !$has_site_id,
    ];
    $form['channel']['site_id'] = [
      '#type' => 'item',
      '#title' => $this->t('Site ID'),
      '#description' => $this->t('Site ID for BigCommerce, always attached to a channel and links to a specific URL.'),
      '#markup' => $config->get('channel.site_id'),
      '#access' => $has_site_id,
    ];
    $form['channel']['site_url'] = [
      '#type' => 'item',
      '#title' => $this->t('Site URL'),
      '#description' => $this->t('Site URL for BigCommerce, must match your Drupal URL for the checkout to load.'),
      '#markup' => $config->get('channel.site_url'),
      '#access' => $has_site_id,
    ];

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
      $catalog_client->catalogSummaryGet();
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

  /**
   * Setup a BigCommerce channel.
   *
   * @param array $settings
   *   An array based on bigcommerce.settings:api.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   Returns a TranslatableMarkup with the connection error or NULL if there
   *   is no error.
   */
  protected function setupChannel(array $settings) {
    try {
      $base_client = new ApiClient(ClientFactory::createApiConfiguration($settings));
      $channel_api = new ChannelsApi($base_client);

      $create_channel_request = new CreateChannelRequest();
      $create_channel_request->setType(CreateChannelRequest::TYPE_STOREFRONT);
      // There currently isn't a Drupal platform listing, use wordpress for now.
      // This will also require an update of the SDK.
      $create_channel_request->setPlatform(CreateChannelRequest::PLATFORM_WORDPRESS);
      $create_channel_request->setName($this->configFactory->getEditable('commerce_store.settings')->get('default_store'));

      $response = $channel_api->createChannel($create_channel_request);
      $channel = $response->getData();

      $this->config('bigcommerce.settings')
        ->set('channel.id', $channel->getId())
        ->set('channel.name', $channel->getName())
        ->save();
    }
    catch (\Exception $e) {
      return $this->t(
        'There was an error setting up a channel via the BigCommerce API ( <a href=":status_url">System Status</a> | <a href=":contact_url">Contact Support</a> ). Connection failed due to: %message',
        [
          ':status_url' => 'http://status.bigcommerce.com/',
          ':contact_url' => 'https://support.bigcommerce.com/contact',
          '%message' => $e->getMessage(),
        ]
      );
    }

    return NULL;
  }

  /**
   * Setup a BigCommerce site.
   *
   * @param array $settings
   *   An array based on bigcommerce.settings:api.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   Returns a TranslatableMarkup with the connection error or NULL if there
   *   is no error.
   */
  protected function setupSite(array $settings) {
    try {
      $base_client = new ApiClient(ClientFactory::createApiConfiguration($settings));
      $sites_api = new SitesApi($base_client);

      $config = $this->config('bigcommerce.settings');

      // See if a site already exists.
      try {
        $response = $sites_api->getChannelSite($config->get('channel.id'));
        $site = $response->getData();
      } catch (ApiException $e) {
      }

      // Create a site if we need to.
      if (empty($site) || empty($site->getId())) {
        $site_create_request = new SiteCreateRequest();
        $site_create_request->setChannelId($config->get('channel.id'));
        $site_create_request->setUrl(\Drupal::urlGenerator()->generateFromRoute('<front>', [], ['absolute' => TRUE]));

        // The API lists both passing the channel id and adding it as a parameter.
        $response = $sites_api->postChannelSite($config->get('channel.id'), $site_create_request);
        $site = $response->getData();
      }

      $config
        ->set('channel.site_id', $site->getId())
        ->set('channel.site_url', $site->getUrl())
        ->save();
    }
    catch (\Exception $e) {
      return $this->t(
        'There was an error setting up a site via the BigCommerce API ( <a href=":status_url">System Status</a> | <a href=":contact_url">Contact Support</a> ). Connection failed due to: %message',
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
