<?php

/**
 * @file
 * Fake an HTTPS request, for use during testing.
 *
 * @todo Fix this to use a new request rather than modifying server variables,
 *   see http.php.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

chdir('../../../..');

define('DRUPAL_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));

require_once DRUPAL_ROOT . '/core/vendor/autoload.php';
include_once DRUPAL_ROOT . '/core/includes/bootstrap.inc';

// Set a global variable to indicate a mock HTTPS request.
$is_https_mock = empty($_SERVER['HTTPS']);

// Change to HTTPS.
$_SERVER['HTTPS'] = 'on';
foreach ($_SERVER as &$value) {
  $value = str_replace('core/modules/system/tests/https.php', 'index.php', $value);
  $value = str_replace('http://', 'https://', $value);
}

$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request);
$kernel->setTestOnly(TRUE);
$response = $kernel->handle($request)->prepare($request)->send();
$kernel->terminate($request, $response);
