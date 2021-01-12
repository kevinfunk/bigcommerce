<?php

namespace Drupal\bigcommerce_stock\API;

/**
 * Interface for working with bigcommerce webhooks.
 *
 * @package Drupal\bigcommerce_stock\API
 */
interface WebhookServiceInterface {

  /**
   * Creates a webhook.
   *
   * @param array $values
   *   Array of values to set in the http body.
   *
   * @return mixed
   *   The webhook data.
   */
  public function create(array $values);

  /**
   * Creates or updates a webhook.
   *
   * @param array $values
   *   Array of values to set in the http body.
   *
   * @return mixed
   *   The webhook data.
   */
  public function createOrUpdate(array $values);

  /**
   * Returns a webhook with a specific id.
   *
   * @param int $id
   *   The id of the webhook.
   *
   * @return mixed
   *   The webhook data.
   */
  public function get($id);

  /**
   * Returns all webhooks.
   *
   * @return array
   *   Array of all webhooks.
   */
  public function getAll();

  /**
   * Updates a webhook with defined values.
   *
   * @param int $id
   *   Id of the webhook.
   * @param array $values
   *   Array of values to set in the http body.
   *
   * @return mixed
   *   The webhook data.
   */
  public function update($id, array $values);

  /**
   * Deletes a webhook with specified id.
   *
   * @param int $id
   *   Id of the webhook.
   *
   * @return mixed
   *   The webhook data.
   */
  public function delete($id);

}
