<?php

namespace Drupal\bigcommerce\Form;

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

    $form['api_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Credentials'),
    ];

    // TODO Add example to description.
    $form['api_settings']['store_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Store Hash'),
      '#description' => $this->t('The hash of your BigCommerce store.'),
      '#default_value' => $config->get('store_hash'),
    ];

    // TODO Add link to docs.
    $form['api_settings']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('The API Client ID from BigCommerce.'),
      '#default_value' => $config->get('client_id'),
    ];

    $form['api_settings']['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('The API Access Token from BigCommerce.'),
      '#default_value' => $config->get('access_token'),
    ];

    $form['api_settings']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('The Client Secret from BigCommerce.'),
      '#default_value' => $config->get('client_secret'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable('bigcommerce.settings')
      // Set the submitted configuration setting.
      ->set('store_hash', $form_state->getValue('store_hash'))
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('access_token', $form_state->getValue('access_token'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      /* Need to verify if form values and settings are correct and reflect the nature of how settings will be handled before any save functionality is done. */
      ->save();

    // Validation of course needed as well.
    parent::submitForm($form, $form_state);
  }

}
