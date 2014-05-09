<?php

/**
 * @file
 * Contains \Drupal\Core\Test\TestKernel.
 */

namespace Drupal\Core\Test;

use Composer\Autoload\ClassLoader;
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel to mock requests to test simpletest.
 */
class TestKernel extends DrupalKernel {

  /**
   * {@inheritdoc}
   */
  public static function createFromRequest(Request $request, $environment, ClassLoader $class_loader, $allow_dumping = TRUE) {
    // Include our bootstrap file.
    require_once dirname(dirname(dirname(__DIR__))) . '/includes/bootstrap.inc';

    // Exit if we should be in a test environment but aren't.
    if (!drupal_valid_test_ua()) {
      header($request->server->get('SERVER_PROTOCOL') . ' 403 Forbidden');
      exit;
    }

    return parent::createFromRequest($request, $environment, $class_loader, $allow_dumping);
  }

}
