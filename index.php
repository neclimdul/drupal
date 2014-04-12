<?php

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */

use Drupal\Core\DrupalKernelFactory;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/core/vendor/autoload.php';
require_once __DIR__ . '/core/includes/bootstrap.inc';

try {
  $request = Request::createFromGlobals();
  $kernel = DrupalKernelFactory::get($request);
  $response = $kernel->handle($request)->prepare($request)->send();
  $kernel->terminate($request, $response);
}
catch (Exception $e) {
  $message = 'If you have just changed code (for example deployed a new module or moved an existing one) read <a href="http://drupal.org/documentation/rebuild">http://drupal.org/documentation/rebuild</a>';
  if (\Drupal\Component\Utility\Settings::get('rebuild_access', FALSE)) {
    $rebuild_path = $GLOBALS['base_url'] . '/rebuild.php';
    $message .= " or run the <a href=\"$rebuild_path\">rebuild script</a>";
  }
  print $message;
  throw $e;
}
