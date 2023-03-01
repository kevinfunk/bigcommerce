<?php

namespace Drupal\Tests\bigcommerce\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests various scenarios whilst installing the module.
 *
 * @group bigcommerce
 */
class InstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bigcommerce',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests uninstalling and reinstalling the module.
   */
  public function testReinstall() {
    $this->drupalLogin($this->createUser(['administer modules']));
    $this->drupalGet('admin/modules/uninstall');
    $edit = ['uninstall[bigcommerce]' => TRUE];
    $this->submitForm($edit, t('Uninstall'));
    $this->submitForm([], t('Uninstall'));
    $this->rebuildContainer();
    $this->assertFalse($this->container->get('module_handler')->moduleExists('bigcommerce'), 'BigCommerce module uninstalled.');
    $this->drupalGet('admin/modules');
    $edit = ["modules[bigcommerce][enable]" => TRUE];
    $this->submitForm($edit, t('Install'));
    $this->rebuildContainer();
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('bigcommerce'), 'BigCommerce module has been installed.');
  }

  /**
   * Tests installing BigCommerce when Commerce Checkout is already installed.
   */
  public function testsCommmerceCheckoutConflict() {
    // There is nothing we can do to prevent commerce_checkout from being
    // installed when BigCommerce is installed so we add a message when this
    // occurs.
    $this->drupalLogin($this->createUser(['administer modules', 'administer site configuration']));
    $this->drupalGet('admin/modules');
    $edit = ["modules[commerce_checkout][enable]" => TRUE];
    $this->submitForm($edit, t('Install'));
    $this->assertSession()->pageTextContains('BigCommerce provides its own checkout functionality which conflicts with Commerce Checkout. Ensure Commerce Checkout is uninstalled before using BigCommerce.');
    $this->rebuildContainer();
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('commerce_checkout'), 'Commerce checkout module has been installed.');
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('BigCommerce provides its own checkout functionality which conflicts with Commerce Checkout. Ensure Commerce Checkout is uninstalled before using BigCommerce.');

    $this->drupalGet('admin/modules/uninstall');
    $edit = ['uninstall[bigcommerce]' => TRUE];
    $this->submitForm($edit, t('Uninstall'));
    $this->submitForm([], t('Uninstall'));
    $this->rebuildContainer();
    $this->assertFalse($this->container->get('module_handler')->moduleExists('bigcommerce'), 'BigCommerce module uninstalled.');

    // This will fail due to bigcommerce_requirements()
    $this->drupalGet('admin/modules');
    $edit = ["modules[bigcommerce][enable]" => TRUE];
    $this->submitForm($edit, t('Install'));
    $this->rebuildContainer();
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('bigcommerce'), 'BigCommerce module has been installed.');
    $this->assertSession()->pageTextContains('BigCommerce provides its own checkout functionality which conflicts with Commerce Checkout. Ensure Commerce Checkout is uninstalled before using BigCommerce.');
  }

}
