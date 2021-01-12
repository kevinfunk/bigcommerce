<?php

namespace Drupal\bigcommerce_stock\Controller;

use BigCommerce\Api\v3\Api\CatalogApi;
use BigCommerce\Api\v3\ApiClient;
use BigCommerce\Api\v3\ApiException;
use Drupal\bigcommerce\ClientFactory;
use Drupal\commerce_stock\StockCheckInterface;
use Drupal\commerce_stock\StockUpdateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Processes and authenticates BigCommerce inventory webhook events.
 */
class WebhookController extends ControllerBase {

  /**
   * The HTTP request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

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
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a WebhookController object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\commerce_stock\StockCheckInterface $stock_checker
   *   The local stock checker.
   * @param \Drupal\commerce_stock\StockUpdateInterface $stock_updater
   *   The stock updater.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Request $request, StockCheckInterface $stock_checker, StockUpdateInterface $stock_updater, EntityTypeManagerInterface $entity_type_manager) {
    $this->request = $request;
    $this->stockChecker = $stock_checker;
    $this->stockUpdater = $stock_updater;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('commerce_stock.local_stock_checker'),
      $container->get('commerce_stock.local_stock_updater'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Listens for webhook events.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An HTTP response code.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function listener() {
    $response = new Response();
    $payload = $this->request->getContent();
    $payload = json_decode($payload, TRUE);

    if (!$payload) {
      $response->setStatusCode(Response::HTTP_BAD_REQUEST);
      return $response;
    }

    if ($payload['data']['inventory']['method'] !== 'absolute') {
      // We skip methods that don't return absolute values but respond with a
      // 200 response so we don't get more.
      $response->setStatusCode(Response::HTTP_OK);
      return $response;
    }

    $variant_remote_id = $payload['data']['inventory']['variant_id'];
    $product_id = $payload['data']['inventory']['product_id'];

    $base_client = new ApiClient(ClientFactory::createApiConfiguration($this->config('bigcommerce.settings')->get('api')));
    $cart_api = new CatalogApi($base_client);

    try {
      $variant = $cart_api->getVariantById($product_id, $variant_remote_id);
    }
    catch (ApiException $e) {
      $response->setStatusCode(Response::HTTP_BAD_REQUEST);
      return $response;
    }

    $sku = $variant->getData()->getSku();

    $variation = $this->entityTypeManager
      ->getStorage('commerce_product_variation')
      ->loadBySku($sku);

    if (!$variation) {
      $this->getLogger('bigcommerce_stock')
        ->error(sprintf('Variation with the sku: %s is missing, please check if the migration completed successfully.', $sku));

      // Locally we couldn't find the variation to be updated. This could be
      // because the migration didn't import the content yet. Send a bad status
      // code so we can try later.
      $response->setStatusCode(Response::HTTP_BAD_REQUEST);
      return $response;
    }

    $location = current($this->entityTypeManager
      ->getStorage('commerce_stock_location')
      ->loadByProperties(['type' => 'bigcommerce']));

    if (!$location) {
      $this->getLogger('bigcommerce_stock')
        ->error('Missing big commerce location.');

      $response->setStatusCode(Response::HTTP_BAD_REQUEST);
      return $response;
    }

    $current_stock_level = $this->stockChecker
      ->getLocationStockLevel($location->id(), $variation)['qty'];

    $inventory_level = $payload['data']['inventory']['value'];

    if ($current_stock_level !== $inventory_level) {
      $this->stockUpdater
        ->setLocationStockLevel($location->id(), $variation, $inventory_level, 0);
    }

    // Invalidate cache for the variation in case we display the stock value
    // on the product page.
    // @see https://www.drupal.org/project/commerce_stock/issues/3021821
    Cache::invalidateTags($variation->getCacheTagsToInvalidate());

    $response->setStatusCode(Response::HTTP_OK);
    return $response;
  }

  /**
   * Checks access for incoming webhook events.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $incoming_username = $this->request->headers->get('Username');
    $incoming_password = $this->request->headers->get('Password');

    $stored_username = $this->config('bigcommerce_stock.settings')->get('username');
    $stored_password = $this->config('bigcommerce_stock.settings')->get('password');

    return AccessResult::allowedIf($incoming_username === $stored_username && $incoming_password === $stored_password);
  }

}
