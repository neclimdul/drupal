<?php

/**
 * @file
 * Fake an HTTPS request, for use during testing.
 *
 * @todo Fix this to use a new request rather than modifying server variables,
 *   see http.php.
 */

use Drupal\Core\DrupalKernelFactory;
use Symfony\Component\HttpFoundation\Request;

chdir('../../../..');

require_once './core/vendor/autoload.php';
require_once './core/includes/bootstrap.inc';

// Set a global variable to indicate a mock HTTPS request.
$is_https_mock = empty($_SERVER['HTTPS']);

// Change to HTTPS.
$_SERVER['HTTPS'] = 'on';
foreach ($_SERVER as &$value) {
  $value = str_replace('core/modules/system/tests/https.php', 'index.php', $value);
  $value = str_replace('http://', 'https://', $value);
}

$request = Request::createFromGlobals();
$kernel = DrupalKernelFactory::get($request);
$kernel->setTestOnly(TRUE);
$response = $kernel
  ->handlePageCache($request)
  ->handle($request)
    ->prepare($request)->send();
$kernel->terminate($request, $response);
