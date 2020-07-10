<?php

namespace Drupal\bigcommerce_stock\EventSubscriber;

use Drupal\commerce_stock\StockCheckInterface;
use Drupal\commerce_stock\StockUpdateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\migrate_plus\Event\MigrateEvents as MigratePlugMigrateEvents;

/**
 * Class VariationStockUpdateSubscriber.
 */
class VariationStockUpdateSubscriber implements EventSubscriberInterface {

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
   * VariationStockUpdateSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_stock\StockCheckInterface $stock_checker
   *   The stock checker.
   * @param \Drupal\commerce_stock\StockUpdateInterface $stock_updater
   *   The stock updater.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StockCheckInterface $stock_checker, StockUpdateInterface $stock_updater) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stockChecker = $stock_checker;
    $this->stockUpdater = $stock_updater;
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

}
