<?php

namespace Drupal\bigcommerce\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\Core\Config\ConfigFactoryInterface;

use BigCommerce\Api\v3\ApiClient;
use BigCommerce\Api\v3\Api\CartApi;
use BigCommerce\Api\v3\Model\CartRequestData;
use BigCommerce\Api\v3\Model\LineItemRequestData;
use Drupal\bigcommerce\ClientFactory;

/**
 * Event Subscriber to handle syncing the Commerce and BigCommerce carts.
 */
class CartEventSubscriber implements EventSubscriberInterface {

  /**
   * The BigCommerce API settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new CartEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('bigcommerce.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      CartEvents::CART_EMPTY => 'bigCommerceCartDelete',
      CartEvents::CART_ENTITY_ADD => 'bigCommerceAddToCart',
      CartEvents::CART_ORDER_ITEM_UPDATE => 'bigCommerceCartUpdate',
      CartEvents::CART_ORDER_ITEM_REMOVE => 'bigCommerceCartRemove',
    ];
    return $events;
  }

  /**
   * Delete BigCommerce cart.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   */
  public function bigCommerceCartDelete(CartEntityAddEvent $event) {
    try {
      $base_client = new ApiClient(ClientFactory::createApiConfiguration($this->config->get('api')));
      $cart_api = new CartApi($base_client);

      $order = $event->getCart();
      $bc_cart_id = $order->getData('bigcommerce_cart_id');

      if ($bc_cart_id) {
        $cart_api->cartsCartIdDelete($bc_cart_id);
      }
    }
    catch (\Exception $e) {
      // Watchdog? not sure what we should do here.
    }
  }

  /**
   * Push the added item to BigCommerce cart.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   */
  public function bigCommerceAddToCart(CartEntityAddEvent $event) {
    try {
      $base_client = new ApiClient(ClientFactory::createApiConfiguration($this->config->get('api')));
      $cart_api = new CartApi($base_client);

      $order_item = $event->getOrderItem();
      $order = $event->getCart();
      $bc_cart_id = $order->getData('bigcommerce_cart_id');

      $request_data = new CartRequestData();
      $request_data->setLineItems([
        new LineItemRequestData([
          'quantity' => $order_item->getQuantity(),
          // Will be a BigCommerce specific ID, change this once that exists.
          'product_id' => '111',
          // Will be a BigCommerce specific ID, change this once that exists.
          // 'variation_id' => $order_item->getPurchasedEntity()->getSku().
        ]),
      ]);

      // Probably turn this into a function so we can stop caring about if we
      // have a cart or not.
      $bc_cart = '';
      if (!$bc_cart_id) {
        $cart_response = $cart_api->cartsPost($request_data);
        if ($cart_response) {
          $bc_cart = $cart_response->getData();
        }
        $bc_cart_id = $bc_cart->getId();
        $order->setData('bigcommerce_cart_id', $bc_cart_id);
      }
      else {
        $cart_response = $cart_api->cartsCartIdItemsPost($bc_cart_id, $request_data);
        if ($cart_response) {
          $bc_cart = $cart_response->getData();
        }
      }

      $bc_line_items = $bc_cart->getLineItems();
      $bc_line_items = array_merge($bc_line_items->getPhysicalItems(), $bc_line_items->getDigitalItems(), $bc_line_items->getGiftCertificates());
      foreach ($bc_line_items as $bc_line_item) {
        // Replace with actual ID once we have it.
        if ($bc_line_item->getProductId() == '111') {
          $order_item->setData('bigcommerce_item_id', $bc_line_item->getId());
        }
      }
    }
    catch (\Exception $e) {
      // Watchdog? not sure what we should do here.
    }
  }

  /**
   * Update an item in BigCommerce cart via item_id.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   */
  public function bigCommerceCartUpdate(CartEntityAddEvent $event) {
    try {
      $base_client = new ApiClient(ClientFactory::createApiConfiguration($this->config->get('api')));
      $cart_api = new CartApi($base_client);

      $order_item = $event->getOrderItem();
      $order = $event->getCart();
      $bc_cart_id = $order->getData('bigcommerce_cart_id');
      $bc_item_id = $order_item->getData('bigcommerce_item_id');

      $request_data = new CartRequestData();
      $request_data->setLineItems([
        new LineItemRequestData([
          'quantity' => $order_item->getQuantity(),
        ]),
      ]);

      if ($bc_cart_id) {
        $cart_api->cartsCartIdItemsItemIdPut($bc_cart_id, $bc_item_id, $request_data);
      }
    }
    catch (\Exception $e) {
      // Watchdog? not sure what we should do here.
    }
  }

  /**
   * Remove an item from BigCommerce cart via item_id.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   */
  public function bigCommerceCartRemove(CartEntityAddEvent $event) {
    try {
      $base_client = new ApiClient(ClientFactory::createApiConfiguration($this->config->get('api')));
      $cart_api = new CartApi($base_client);

      $order_item = $event->getOrderItem();
      $order = $event->getCart();
      $bc_cart_id = $order->getData('bigcommerce_cart_id');
      $bc_item_id = $order_item->getData('bigcommerce_item_id');

      if ($bc_cart_id) {
        $cart_api->cartsCartIdItemsItemIdDelete($bc_cart_id, $bc_item_id);
      }
    }
    catch (\Exception $e) {
      // Watchdog? not sure what we should do here.
    }
  }

}