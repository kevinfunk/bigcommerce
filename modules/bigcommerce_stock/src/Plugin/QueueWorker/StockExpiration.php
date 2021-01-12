<?php

namespace Drupal\bigcommerce_stock\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes expired reserved stock.
 *
 * @QueueWorker(
 *  id = "bigcommerce_stock_reserved_stock_expiration",
 *  title = @Translation("Reserved stock expiration"),
 *  cron = {"time" = 30}
 * )
 */
class StockExpiration extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new StockExpiration object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($transaction_id) {
    $location = current($this->entityTypeManager
      ->getStorage('commerce_stock_location')
      ->loadByProperties(['type' => 'bigcommerce']));

    // Check for transaction partner.
    $transaction = \Drupal::database()->select('commerce_stock_transaction', 'st')
      ->fields('st')
      // We can figure out transactions that are related to bigcommerce based on
      // the shipped location.
      ->condition('id', $transaction_id)
      ->condition('location_id', $location->id())
      ->execute()->fetch();

    if (!$transaction) {
      return;
    }

    $query = \Drupal::database()->delete('commerce_stock_transaction');
    $query->condition('id', $transaction_id);
    $query->execute();

    // If the order was created for bigcommerce we have a matching opposite
    // transaction that we need to clear as well so we don't mess up the stock
    // value.
    $sibling_transaction = \Drupal::database()->select('commerce_stock_transaction', 'st')
      ->fields('st')
      ->condition('related_oid', $transaction->related_oid)
      ->condition('id', $transaction_id, '!=')
      ->condition('entity_id', $transaction->entity_id)
      ->condition('location_id', $location->id())
      ->execute()->fetch();

    if ($sibling_transaction) {
      $query = \Drupal::database()->delete('commerce_stock_transaction');
      $query->condition('id', $sibling_transaction['id']);
      $query->execute();
    }
  }

}
