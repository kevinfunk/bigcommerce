<?php

namespace Drupal\Tests\bigcommerce\Functional;

use Drupal\Core\Url;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests syncing product categories from BigCommerce.
 *
 * @group bigcommerce
 */
class ProductCategorySyncTest extends BrowserTestBase implements MigrateMessageInterface {

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
      ->loadByProperties(['vid' => 'bigcommerce_product_category']);
    $this->assertCount(0, $terms);
    $this->executeMigrations('bigcommerce_product_category');
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'bigcommerce_product_category']);
    $this->assertCount(6, $terms);

    // Test the bigcommerce_id field.
    $this->assertSame(20, Term::load(1)->bigcommerce_id->value);
    $this->assertSame(18, Term::load(2)->get('bigcommerce_id')->value);
    $this->assertSame(19, Term::load(3)->get('bigcommerce_id')->value);
    $this->assertSame(21, Term::load(4)->get('bigcommerce_id')->value);
    $this->assertSame(22, Term::load(5)->get('bigcommerce_id')->value);
    $this->assertSame(23, Term::load(6)->get('bigcommerce_id')->value);

    // Test terms not created via syncing are not broken.
    $term = Term::create([
      'vid' => 'bigcommerce_product_category',
      'name' => 'a fake test',
    ]);
    $this->assertNull($term->get('bigcommerce_id')->value);
    $term->save();
    $this->assertNull(Term::load(7)->get('bigcommerce_id')->value);
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
