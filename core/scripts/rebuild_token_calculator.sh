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

$autoloader = require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/bootstrap.inc';

if (!drupal_is_cli()) {
  exit;
}
$request = Request::createFromGlobals();
$kernel = new DrupalKernel('prod', $autoloader);
$kernel->boot($request);

$timestamp = time();
$token = Crypt::hmacBase64($timestamp, Settings::get('hash_salt'));

print "timestamp=$timestamp&token=$token\n";
