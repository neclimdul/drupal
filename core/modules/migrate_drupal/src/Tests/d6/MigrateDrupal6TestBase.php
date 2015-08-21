<?php

/**
 * @file
 * Contains \Drupal\migrate_drupal\Tests\d6\MigrateDrupal6TestBase.
 */

namespace Drupal\migrate_drupal\Tests\d6;

use Drupal\migrate_drupal\Tests\MigrateDrupalTestBase;

/**
 * Base class for Drupal 6 migration tests.
 */
abstract class MigrateDrupal6TestBase extends MigrateDrupalTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
      $this->loadDump(__DIR__ . '/../../../tests/fixtures/drupal-6.standard.php');
    $this->installMigrations('Drupal 6');
  }
}
