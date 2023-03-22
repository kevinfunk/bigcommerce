<?php

namespace Drupal\bigcommerce_stock\Form;

use BigCommerce\Api\v3\ApiClient;
use BigCommerce\Api\v3\Api\CatalogApi;
use BigCommerce\Api\v3\ApiException;
use Drupal\bigcommerce\ClientFactory;
use Drupal\bigcommerce_stock\API\WebhookServiceInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Configure webhook settings.
 *
 * @package Drupal\bigcommerce_stock\Form
 */
class WebhookSettingsForm extends ConfigFormBase {

  /**
   * The webhook service.
   *
   * @var \Drupal\bigcommerce_stock\API\WebhookServiceInterface
   */
  protected $webhookService;

  /**
   * The request stack used to determine current time.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a WebhookSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\bigcommerce_stock\API\WebhookServiceInterface $webhook_service
   *   The webhook service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ConfigFactoryInterface $config_factory, WebhookServiceInterface $webhook_service, RequestStack $request_stack) {
    parent::__construct($config_factory);
    $this->webhookService = $webhook_service;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('bigcommerce_stock.webhook_service'),
      $container->get('request_stack')
    );
  }

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

    $config = $this->config('bigcommerce_stock.settings');

    $form['webhook'] = [
      '#type' => 'details',
      '#title' => $this->t('Register a stock webhook'),
      '#open' => TRUE,
    ];

    $form['reserve'] = [
      '#type' => 'details',
      '#title' => $this->t('Reserve stock'),
      '#open' => TRUE,
    ];

    $form['reserve']['reserve_stock'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reserve stock on add to cart'),
      '#default_value' => $config->get('reserve_stock'),
    ];

    $form['reserve']['reserve_expiration'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['interval'],
      ],
      '#states' => [
        'visible' => [
          ':input[name="reserve_stock"]' => ['checked' => TRUE],
        ],
      ],
      '#open' => TRUE,
    ];

    $reserve_number = $config->get('reserve_number');
    $form['reserve']['reserve_expiration']['reserve_number'] = [
      '#type' => 'number',
      '#title' => t('Interval'),
      '#default_value' => !empty($reserve_number) ? $reserve_number : 30,
      '#required' => TRUE,
      '#min' => 1,
    ];

    $reserve_unit = $config->get('reserve_unit');
    $form['reserve']['reserve_expiration']['reserve_unit'] = [
      '#type' => 'select',
      '#title' => t('Unit'),
      '#title_display' => 'invisible',
      '#default_value' => !empty($reserve_unit) ? $reserve_unit : 'day',
      '#options' => [
        'minute' => t('Minute'),
        'hour' => t('Hour'),
        'day' => t('Day'),
        'month' => t('Month'),
      ],
      '#required' => TRUE,
    ];

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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->requestStack->getCurrentRequest()->isSecure()) {
      return;
    }

    $this->messenger()->addError($this->t('You can only register a webhook from a HTTPS destination.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('bigcommerce_stock.settings');

    $config->set('reserve_stock', $form_state->getValue('reserve_stock'));
    $config->set('reserve_number', $form_state->getValue('reserve_number'));
    $config->set('reserve_unit', $form_state->getValue('reserve_unit'));
    $config->save();

    $webhookValues = [
      'scope' => 'store/sku/inventory/updated',
      'is_active' => TRUE,
      'headers' => [
        'username' => $form_state->getValue('username', $config->get('username')),
        'password' => $form_state->getValue('password', $config->get('password')),
      ],
    ];

    try {
      $webhook = $this->webhookService->createOrUpdate($webhookValues);

      $config->set('username', $webhook->headers->username)
        ->set('password', $webhook->headers->password)
        ->set('id', $webhook->id)
        ->set('destination', $webhook->destination)
        ->save();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
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
    $config = $this->configFactory()->getEditable('bigcommerce_stock.settings');
    try {
      if ($webhook_id = $config->get('id')) {
        $this->webhookService->delete($webhook_id);
      }

      $config->clear('username');
      $config->clear('password');
      $config->clear('id');
      $config->clear('destination');
      $config->save();
    }
    catch (ApiException $e) {
      $response_body = $e->getResponseBody();
      if ($response_body->status === 404) {
        $config->clear('username');
        $config->clear('password');
        $config->clear('id');
        $config->clear('destination');
        $config->save();

        return;
      }

      $this->messenger()->addError($response_body->title);
    }
  }

}
