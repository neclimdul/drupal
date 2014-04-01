#!/usr/bin/env php
<?php

/**
 * @file
 * Command line token calculator for rebuild.php.
 */

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Settings;
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/bootstrap.inc';

if (!drupal_is_cli()) {
  exit;
}
$request = Request::createFromGlobals();
DrupalKernel::bootConfiguration($request);

$timestamp = time();
$token = Crypt::hmacBase64($timestamp, Settings::get('hash_salt'));

print "timestamp=$timestamp&token=$token\n";
