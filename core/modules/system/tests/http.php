<?php
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

/**
 * @file
 * Fake an HTTP request, for use during testing.
 */

// Change current directory to the Drupal root.
chdir('../../../..');
define('DRUPAL_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));
require_once DRUPAL_ROOT . '/core/vendor/autoload.php';
include_once DRUPAL_ROOT . '/core/includes/bootstrap.inc';

// Set a global variable to indicate a mock HTTP request.
$is_http_mock = !empty($_SERVER['HTTPS']);
// Change to HTTP.
$_SERVER['HTTPS'] = NULL;
foreach ($_SERVER as &$value) {
  $value = str_replace('core/modules/system/tests/http.php', 'index.php', $value);
  $value = str_replace('http://', 'https://', $value);
}
ini_set('session.cookie_secure', FALSE);
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request);
$kernel->setTestOnly(TRUE);
$response = $kernel->handle($request)->prepare($request)->send();
$kernel->terminate($request, $response);
