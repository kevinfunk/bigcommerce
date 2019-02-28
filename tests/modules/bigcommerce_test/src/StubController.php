<?php

namespace Drupal\bigcommerce_test;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class StubController
 *
 * @package Drupal\bigcommerce_test
 */
class StubController extends ControllerBase {

  /**
   * @param $folder
   * @param $client
   * @param $command
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function get($folder, $client, $command) {
    $filename = realpath(__DIR__ . '/..') . '/stubs/' . $folder . '/' . $client . '_' . $command . '.json';
    $json = @file_get_contents($filename);
    if ($json) {
      $data = json_decode($json, TRUE);
      return new JsonResponse($data, $data['status'] ?? 200);
    }
    // Throw a more helpful exception.
    throw new \RuntimeException(sprintf("Can not find stub file: %s", $filename));
  }

}
