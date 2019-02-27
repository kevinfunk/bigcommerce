<?php

namespace Drupal\Tests\bigcommerce\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the administering the BigCommerce module.
 *
 * @group bigcommerce
 */
class AdminTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bigcommerce',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * Tests.
   */
  public function testAdminPage() {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/commerce/config/bigcommerce');
    $assert->pageTextContains('Access denied');

    $this->drupalLogin($this->drupalCreateUser(['access bigcommerce administration pages']));
    $this->drupalGet('admin/commerce/config/bigcommerce');
    $page->clickLink('BigCommerce Settings');
    $page->fillField('store_hash', 'a hash value');
    $page->fillField('client_id', 'a client id');
    $page->fillField('access_token', 'an access token');
    $this->htmlOutput();
    $page->findButton('Save configuration')->click();
    $this->htmlOutput();
    $assert->pageTextContains('The configuration options have been saved.');
    $config = $this->config('bigcommerce.settings');
    $this->assertEquals('a hash value', $config->get('store_hash'));
    $this->assertEquals('a client id', $config->get('client_id'));
    $this->assertEquals('an access token', $config->get('access_token'));
    $this->assertSession()->fieldValueEquals('store_hash', 'a hash value');
    $this->assertSession()->fieldValueEquals('client_id', 'a client id');
    $this->assertSession()->fieldValueEquals('access_token', 'an access token');
  }

}
