<?php

namespace Drupal\bigcommerce\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is fired the cart it finalized.
 */
class FinalizeCartEvent extends Event {

  const EVENT_NAME = 'bigcommerce.finalize_cart';

  /**
   * The finalized order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Constructs the object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The finalized order.
   */
  public function __construct(OrderInterface $order) {
    $this->order = $order;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   Gets the order.
   */
  public function getOrder() {
    return $this->order;
  }

}
