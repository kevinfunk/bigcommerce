<?php

namespace Drupal\bigcommerce_stock;

use Drupal\commerce\Interval;
use Drupal\commerce_stock\StockTransactionsInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Default cron implementation.
 *
 * Queues reserved stock for expiration.
 */
class Cron implements CronInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The commerce_cart_expiration queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new Cron object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue_factory->get('bigcommerce_stock_reserved_stock_expiration');
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    if (!\Drupal::config('bigcommerce_stock.settings')->get('reserve_stock')) {
      return;
    }

    $location = current($this->entityTypeManager
      ->getStorage('commerce_stock_location')
      ->loadByProperties(['type' => 'bigcommerce']));

    $reserve_number = \Drupal::config('bigcommerce_stock.settings')->get('reserve_number');
    $reserve_unit = \Drupal::config('bigcommerce_stock.settings')->get('reserve_unit');
    $interval = new Interval($reserve_number, $reserve_unit);

    $current_date = new DrupalDateTime('now');
    $expiration_date = $interval->subtract($current_date);
    $expiration_timestamp = $expiration_date->getTimestamp();

    $transactions = $this->database->select('commerce_stock_transaction', 'st')
      ->fields('st')
      // We can figure out transactions that are related to bigcommerce based on
      // the shipped location.
      ->condition('location_id', $location->id())
      ->condition('transaction_time', $expiration_timestamp, '<=')
      // Only get the transactions that remove stock.
      ->condition('transaction_type_id', StockTransactionsInterface::STOCK_OUT)
      ->execute()->fetchAllAssoc('id');

    foreach ($transactions as $id => $data) {
      $this->queue->createItem($id);
    }
  }

}
