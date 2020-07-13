<?php

namespace Drupal\bigcommerce_stock\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_stock\StockCheckInterface;
use Drupal\commerce_stock\StockUpdateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\migrate_plus\Event\MigrateEvents as MigratePlugMigrateEvents;

/**
 * Class VariationStockUpdateSubscriber.
 */
class VariationStockUpdateSubscriber implements EventSubscriberInterface {

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
    // This event is in case the migration is rerun.
    $events[MigratePlugMigrateEvents::PREPARE_ROW][] = ['updateVariationStock'];

    // We need a separate event for the migration running for the first time.
    $events[MigrateEvents::POST_ROW_SAVE][] = ['updateVariationStock'];

    // Setting the weight so this event fires before bigCommerceAddToCart.
    $events[CartEvents::CART_ENTITY_ADD][] = ['enforceStockValueOnAdd', 100];
    $events[CartEvents::CART_ORDER_ITEM_UPDATE][] = ['enforceStockValueOnUpdate', 100];

    return $events;
  }

  /**
   * This method is called when the migration events are dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function updateVariationStock(Event $event) {
    $migration = $event->getMigration();
    if ($migration->id() !== 'bigcommerce_product_variation') {
      return;
    }

    if ($migration->getStatus() !== MigrationInterface::STATUS_IMPORTING) {
      return;
    }

    $row = $event->getRow();
    $inventory_level = $row->getSourceProperty('inventory_level');
    $sku = $row->getSourceProperty('sku');
    $variation = $this->entityTypeManager
      ->getStorage('commerce_product_variation')
      ->loadBySku($sku);

    if (!$variation) {
      // We are in a phase where the variation is not ready yet, skip for now
      // and return later. We use the PREPARE_ROW event so that we also
      // handle when the user had already run the bigcommerce_product_variation
      // migration but didn't install the bigcommerce_stock module.
      return;
    }

    // We provide the location with our config on install.
    $location = current($this->entityTypeManager
      ->getStorage('commerce_stock_location')
      ->loadByProperties(['type' => 'bigcommerce']));
    $current_stock_level = $this->stockChecker
      ->getLocationStockLevel($location->id(), $variation)['qty'];

    if ($current_stock_level !== $inventory_level) {
      $latest_txn = $this->stockChecker->getLocationStockTransactionLatest($location->id(), $variation);
      $this->stockUpdater
        ->setLocationStockLevel($location->id(), $variation, $inventory_level, $latest_txn);
    }
  }

  /**
   * Enforcing maximum and minimum order item quantity for the add event.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enforceStockValueOnAdd(CartEntityAddEvent $event) {
    $added_order_item = $event->getOrderItem();
    $this->enforceStockValue($added_order_item);
  }

  /**
   * Enforcing maximum and minimum order item quantity for the update event.
   *
   * @param \Drupal\commerce_cart\Event\CartOrderItemUpdateEvent $event
   *   The add to cart event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enforceStockValueOnUpdate(CartOrderItemUpdateEvent $event) {
    $updated_order_item = $event->getOrderItem();
    $this->enforceStockValue($updated_order_item);
  }

  /**
   * Enforcing maximum and minimum order item quantity.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item to check.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function enforceStockValue(OrderItemInterface $order_item) {
    if (!$this->configFactory->get('bigcommerce_stock.order_item_quantity')->get('order_item_quantity_global')) {
      return;
    }

    $maximum = $this->configFactory->get('bigcommerce_stock.order_item_quantity')->get('order_item_quantity_maximum');
    $minimum = $this->configFactory->get('bigcommerce_stock.order_item_quantity')->get('order_item_quantity_minimum');

    if ($order_item->getQuantity() > $maximum) {
      $order_item->setQuantity((string) $maximum);
      $order_item->save();
      $this->messenger()->addWarning(t('Maximum allowed quantity is @max, changing @product quantity to the maximum allowed value.', [
        '@max' => $maximum,
        '@product' => $order_item->getPurchasedEntity()->getOrderItemTitle(),
      ]));
    }

    if ($order_item->getQuantity() < $minimum) {
      $order_item->setQuantity((string) $minimum);
      $order_item->save();
      $this->messenger()->addWarning(t('Minimum allowed quantity is @min, changing @product quantity to the minimum allowed value.', [
        '@min' => $minimum,
        '@product' => $order_item->getPurchasedEntity()->getOrderItemTitle(),
      ]));
    }
  }

}
