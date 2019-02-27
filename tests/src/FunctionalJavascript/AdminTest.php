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

    // These are not actual keys, but they match the same length and format.
    $page->fillField('store_hash', 'qtr7v94hi');
    $page->fillField('client_id', 'nqyp6fpkwp36z6u25epgsm2qlsishgt');
    $page->fillField('access_token', 'tk9trfgscj16mirm89vxredsmfgfhjp');
    $page->fillField('client_secret', '8mo1qu0rlhq8ib009xha71amyg6k9dl');

    $page->findButton('Save configuration')->click();

    $assert->pageTextContains('The configuration options have been saved.');
    $config = $this->config('bigcommerce.settings');
    $this->assertEquals('qtr7v94hi', $config->get('store_hash'));
    $this->assertEquals('nqyp6fpkwp36z6u25epgsm2qlsishgt', $config->get('client_id'));
    $this->assertEquals('tk9trfgscj16mirm89vxredsmfgfhjp', $config->get('access_token'));
    $this->assertEquals('8mo1qu0rlhq8ib009xha71amyg6k9dl', $config->get('client_secret'));
    $this->assertSession()->fieldValueEquals('store_hash', 'qtr7v94hi');
    $this->assertSession()->fieldValueEquals('client_id', 'nqyp6fpkwp36z6u25epgsm2qlsishgt');
    $this->assertSession()->fieldValueEquals('access_token', 'tk9trfgscj16mirm89vxredsmfgfhjp');
    $this->assertSession()->fieldValueEquals('client_secret', '8mo1qu0rlhq8ib009xha71amyg6k9dl');
  }

}
