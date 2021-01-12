<?php

namespace Drupal\bigcommerce_stock\EventSubscriber;

use Drupal\bigcommerce\Event\FinalizeCartEvent;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_stock\StockCheckInterface;
use Drupal\commerce_stock\StockTransactionsInterface;
use Drupal\commerce_stock\StockUpdateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class VariationStockReserveSubscriber.
 */
class VariationStockReserveSubscriber implements EventSubscriberInterface {

  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The stock checker.
   *
   * @var \Drupal\commerce_stock\StockCheckInterface
   */
  protected $stockChecker;

  /**
   * The stock updater.
   *
   * @var \Drupal\commerce_stock\StockUpdateInterface
   */
  protected $stockUpdater;

  /**
   * Config Factory Service Object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * VariationStockUpdateSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_stock\StockCheckInterface $stock_checker
   *   The stock checker.
   * @param \Drupal\commerce_stock\StockUpdateInterface $stock_updater
   *   The stock updater.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StockCheckInterface $stock_checker, StockUpdateInterface $stock_updater, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stockChecker = $stock_checker;
    $this->stockUpdater = $stock_updater;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    // Setting the weight so this event fires after bigCommerceAddToCart.
    $events[CartEvents::CART_ENTITY_ADD][] = ['removeStock', -100];

    $events[FinalizeCartEvent::EVENT_NAME][] = ['returnStock'];

    return $events;
  }

  /**
   * Reserves the stock.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The cart entity add event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function removeStock(CartEntityAddEvent $event) {
    if (!$this->configFactory->get('bigcommerce_stock.settings')->get('reserve_stock')) {
      return;
    }

    $order_item = $event->getOrderItem();
    $quantity = -1 * abs((int) $event->getQuantity());
    $location = current($this->entityTypeManager
      ->getStorage('commerce_stock_location')
      ->loadByProperties(['type' => 'bigcommerce']));

    $metadata = [
      'related_oid' => $order_item->getOrderId(),
    ];

    $this->stockUpdater->createTransaction(
        $event->getEntity(),
        $location->id(),
        '',
        $quantity,
        $order_item->getUnitPrice()->getNumber(),
        $order_item->getUnitPrice()->getCurrencyCode(),
        StockTransactionsInterface::STOCK_OUT,
        $metadata
      );
  }

  /**
   * Returns the stock value on finalizing cart.
   *
   * We rely on the webhook to update the stock to the right value.
   *
   * @param \Drupal\bigcommerce\Event\FinalizeCartEvent $event
   *   The finalize cart event.
   */
  public function returnStock(FinalizeCartEvent $event) {
    if (!$this->configFactory->get('bigcommerce_stock.settings')->get('reserve_stock')) {
      return;
    }

    $order = $event->getOrder();
    foreach ($order->getItems() as $order_item) {
      $quantity = abs((int) $order_item->getQuantity());

      $location = current($this->entityTypeManager
        ->getStorage('commerce_stock_location')
        ->loadByProperties(['type' => 'bigcommerce']));

      $metadata = [
        'related_oid' => $order_item->getOrderId(),
      ];

      $this->stockUpdater->createTransaction(
        $order_item->getPurchasedEntity(),
        $location->id(),
        '',
        $quantity,
        $order_item->getUnitPrice()->getNumber(),
        $order_item->getUnitPrice()->getCurrencyCode(),
        StockTransactionsInterface::STOCK_IN,
        $metadata
      );
    }
  }

}
