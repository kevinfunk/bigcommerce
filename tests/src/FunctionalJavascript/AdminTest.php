<?php

namespace Drupal\Tests\bigcommerce\FunctionalJavascript;

use Drupal\Core\Url;
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
    'bigcommerce_test',
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
    $this->htmlOutput();
    $assert->pageTextNotContains('Connection status');
    $api_path = Url::fromUri('base://bigcommerce_stub/connection')->setAbsolute()->toString();
    $page->fillField('api_settings[path]', $api_path);
    $page->fillField('api_settings[access_token]', 'an access token');
    $page->fillField('api_settings[client_id]', 'a client ID');
    $page->fillField('api_settings[client_secret]', 'a client secret');

    $assert->pageTextContains('No channel is currently configured, once you provide valid API credentials this can be configured.');
    $assert->pageTextNotContains('Site ID');

    $this->htmlOutput();
    $page->findButton('Save configuration')->click();
    $this->htmlOutput();
    $assert->pageTextContains('The configuration options have been saved.');
    $assert->pageTextContains('Connection status');
    $assert->pageTextContains('Connected successfully.');
    $assert->pageTextContains('No channel is currently configured, once you provide valid API credentials this can be configured.');
    $assert->pageTextNotContains('Site ID');

    $config = $this->config('bigcommerce.settings');
    $this->assertEquals([
      'path' => $api_path,
      'access_token' => 'an access token',
      'client_id' => 'a client ID',
      'client_secret' => 'a client secret',
      'timeout' => 15,
    ], $config->get('api'));
    $assert->fieldValueEquals('api_settings[path]', $api_path);
    $assert->fieldValueEquals('api_settings[access_token]', 'an access token');
    $assert->fieldValueEquals('api_settings[client_id]', 'a client ID');
    $assert->fieldValueEquals('api_settings[client_secret]', 'a client secret');
    $page->selectFieldOption('channel_select', "Test channel");
    $this->htmlOutput();
    $page->findButton('Save configuration')->click();
    $this->htmlOutput();
    $assert->pageTextNotContains('No channel is currently configured, once you provide valid API credentials this can be configured.');
    $assert->pageTextContains('Site ID 3');
    $assert->pageTextContains('Site URL http://my_commerce_site.com');
    $this->refreshVariables();
    $this->assertSame(14581, $this->config('bigcommerce.settings')->get('channel_id'));

    // Change to a stub with multiple channels.
    $page->fillField('api_settings[path]', Url::fromUri('base://bigcommerce_stub/multiple_channels/')->setAbsolute()->toString());
    $page->findButton('Save configuration')->click();
    $this->htmlOutput();
    $assert->pageTextContains('Site ID 3');
    $assert->pageTextContains('Site URL http://my_commerce_site.com');
    $page->selectFieldOption('channel_select', "Another test channel");
    $page->findButton('Save configuration')->click();
    $this->htmlOutput();
    $assert->pageTextContains('Site ID 4');
    $assert->pageTextContains('Site URL http://test.my_commerce_site.com');
    $this->refreshVariables();
    $this->assertSame(14582, $this->config('bigcommerce.settings')->get('channel_id'));

    $page->fillField('api_settings[path]', Url::fromUri('base://bigcommerce_stub/connection_failed/')->setAbsolute()->toString());
    $page->findButton('Save configuration')->click();
    $assert->pageTextContains('The configuration options have been saved.');
    $assert->pageTextContains('Connection status');
    $assert->pageTextContains('There was an error connecting to the BigCommerce API');
  }

}
