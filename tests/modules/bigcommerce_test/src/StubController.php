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
   * @param $part1
   * @param $part2
   * @param $part3
   * @param $part4
   * @param $part5
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function get($folder, $part1, $part2, $part3, $part4, $part5) {
    $parts = array_filter([$part1, $part2, $part3, $part4, $part5]);
    $filename = realpath(__DIR__ . '/..') . '/stubs/' . $folder . '/' . implode('_', $parts) . '.json';
    $json = @file_get_contents($filename);
    if ($json) {
      $data = json_decode($json, TRUE);
      return new JsonResponse($data, $data['status'] ?? 200);
    }
    // Throw a more helpful exception.
    throw new \RuntimeException(sprintf("Can not find stub file: %s", $filename));
  }

}
