<?php

namespace Drupal\Tests\bigcommerce\Functional;

use Drupal\Core\Url;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests syncing brands from BigCommerce.
 *
 * @group bigcommerce
 */
class BrandsSyncTest extends BrowserTestBase implements MigrateMessageInterface {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'bigcommerce',
    'bigcommerce_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Configure API to use the stub.
    $this->config('bigcommerce.settings')
      ->set('api.path', Url::fromUri('base://bigcommerce_stub/connection')->setAbsolute()->toString())
      ->set('api.access_token', 'an access token')
      ->set('api.client_id', 'a client ID')
      ->set('api.client_secret', 'a client secret')
      ->save();
  }

  public function testSync() {
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'bigcommerce_product_brand']);
    $this->assertCount(0, $terms);
    $this->executeMigrations('bigcommerce_product_brand');
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'bigcommerce_product_brand']);
    $this->assertCount(5, $terms);

    // Test the bigcommerce_id field.
    $apple_term = Term::load(5);
    $this->assertSame(39, $apple_term->bigcommerce_id->value);
    $this->assertSame('Apple', $apple_term->label());
    $this->assertSame('Apple', $apple_term->field_product_brand_image->alt);
    $this->assertSame('public://bigcommerce/product-brand/apple.jpg', $apple_term->field_product_brand_image->entity->getFileUri());
    $this->assertFileExists($apple_term->field_product_brand_image->entity->getFileUri());
  }

  /**
   * Executes a set of migrations in dependency order.
   *
   * @param string|string[] $ids
   *   Array of migration IDs, in any order or a single ID.
   */
  protected function executeMigrations($ids) {
    $manager = $this->container->get('plugin.manager.migration');
    $ids = (array) $ids;
    array_walk($ids, function ($id) use ($manager) {
      // This is possibly a base plugin ID and we want to run all derivatives.
      $instances = $manager->createInstances($id);
      array_walk($instances, function (MigrationInterface $migration) {
        (new MigrateExecutable($migration, $this))->import();
      });
    });
  }

  public function display($message, $type = 'status') {
  }

}
