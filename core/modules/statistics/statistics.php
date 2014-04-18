<?php

/**
 * @file
 * Handles counts of node views via AJAX with minimal bootstrap.
 */

use Drupal\Core\DrupalKernelFactory;
use Symfony\Component\HttpFoundation\Request;

chdir('../../..');

require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$kernel = DrupalKernelFactory::get(Request::createFromGlobals());

$views = $kernel->getContainer()
  ->get('config.factory')
  ->get('statistics.settings')
  ->get('count_content_views');
if ($views) {
  $nid = filter_input(INPUT_POST, 'nid', FILTER_VALIDATE_INT);
  if ($nid) {
    \Drupal::database()->merge('node_counter')
      ->key('nid', $nid)
      ->fields(array(
        'daycount' => 1,
        'totalcount' => 1,
        'timestamp' => REQUEST_TIME,
      ))
      ->expression('daycount', 'daycount + 1')
      ->expression('totalcount', 'totalcount + 1')
      ->execute();
  }
}

