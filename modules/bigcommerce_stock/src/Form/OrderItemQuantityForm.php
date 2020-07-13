<?php

namespace Drupal\bigcommerce_stock\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class OrderItemQuantityForm.
 *
 * @package Drupal\bigcommerce_stock\Form
 */
class OrderItemQuantityForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bigcommerce_stock.order_item_quantity';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'bigcommerce_stock.order_item_quantity',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('bigcommerce_stock.order_item_quantity');

    $form['order_item_quantity'] = [
      '#type' => 'details',
      '#title' => $this->t('Limit the quantity per order item'),
      '#open' => TRUE,
    ];

    $form['order_item_quantity']['order_item_quantity_global'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set global limit for all products'),
      '#default_value' => $config->get('order_item_quantity_global'),
    ];

    $form['order_item_quantity']['order_item_quantity_minimum'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Purchase Quantity'),
      '#description' => $this->t('Setting this to a positive integer will enforce a minimum quantity limit when customers are ordering from your store.'),
      '#default_value' => $config->get('order_item_quantity_minimum'),
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="order_item_quantity_global"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['order_item_quantity']['order_item_quantity_maximum'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Purchase Quantity'),
      '#description' => $this->t('Setting this to a positive integer will enforce a maximum quantity limit when customers are ordering from your store.'),
      '#default_value' => $config->get('order_item_quantity_maximum'),
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="order_item_quantity_global"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('order_item_quantity_global')) {
      return;
    }

    if (!($form_state->getValue('order_item_quantity_minimum') <= $form_state->getValue('order_item_quantity_maximum'))) {
      $form_state->setErrorByName('order_item_quantity_minimum', 'Minimum needs to be lower then the maximum');
      $form_state->setErrorByName('order_item_quantity_maximum', 'Maximum needs to be bigger then the minimum');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('bigcommerce_stock.order_item_quantity');

    $config->set('order_item_quantity_global', $form_state->getValue('order_item_quantity_global'));
    if ($form_state->getValue('order_item_quantity_global')) {
      $config->set('order_item_quantity_minimum', $form_state->getValue('order_item_quantity_minimum'))
        ->set('order_item_quantity_maximum', $form_state->getValue('order_item_quantity_maximum'));
    }
    else {
      $config->clear('order_item_quantity_minimum');
      $config->clear('order_item_quantity_maximum');
    }

    $config->save();
  }

}
