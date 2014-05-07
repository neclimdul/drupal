<?php

/**
 * @file
 * Rebuilds all Drupal caches even when Drupal itself does not work.
 *
 * Needs a token query argument which can be calculated using the
 * scripts/rebuild_token_calculator.sh script.
 *
 * @see drupal_rebuild()
 */

use Drupal\Component\Utility\Crypt;
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Site\Settings;

// Change the directory to the Drupal root.
chdir('..');

$autoloader = require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/utility.inc';

$request = Request::createFromGlobals();
$kernel = new DrupalKernel('prod', $autoloader);
$response = $kernel->prepareLegacyRequest($request);

if (Settings::get('rebuild_access', FALSE) ||
  (isset($_GET['token'], $_GET['timestamp']) &&
    ((REQUEST_TIME - $_GET['timestamp']) < 300) &&
    ($_GET['token'] === Crypt::hmacBase64($_GET['timestamp'], Settings::get('hash_salt')))
  )) {

  drupal_rebuild($kernel, $request);
  drupal_set_message('Cache rebuild complete.');
}

header('Location: ' . $GLOBALS['base_url']);
