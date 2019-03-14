<?php

namespace Drupal\bigcommerce_test;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Small facade to create URLs for files used by stubs.
 */
class StubFile {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * StubFile constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler) {
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Creates an absolute URL to file.
   *
   * @param string $filename
   *   The filename to create an absolute URL for.
   *
   * @return string
   *   Absolute URL to the file.
   */
  public function createUrl($filename) {
    $uri = $this->moduleHandler->getModule('bigcommerce_test')->getPath() . '/stubs/files/' . $filename;
    return file_create_url($uri);
  }

}
