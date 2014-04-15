<?php

/**
 * @file
 * Fake an HTTP request, for use during testing.
 */

use Drupal\Core\DrupalKernelFactory;
use Symfony\Component\HttpFoundation\Request;

chdir('../../../..');

require_once './core/vendor/autoload.php';
require_once './core/includes/bootstrap.inc';

// Set a global variable to indicate a mock HTTP request.
$is_http_mock = !empty($_SERVER['HTTPS']);

// Change to HTTP.
$_SERVER['HTTPS'] = NULL;
ini_set('session.cookie_secure', FALSE);
foreach ($_SERVER as &$value) {
  $value = str_replace('core/modules/system/tests/http.php', 'index.php', $value);
  $value = str_replace('https://', 'http://', $value);
}

$request = Request::createFromGlobals();
$kernel = DrupalKernelFactory::get($request);
$kernel->setTestOnly(TRUE);
$response = $kernel
  ->handlePageCache($request)
  ->handle($request)
    ->prepare($request)->send();
$kernel->terminate($request, $response);
