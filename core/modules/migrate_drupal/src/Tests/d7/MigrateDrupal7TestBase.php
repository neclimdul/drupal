<?php

/**
 * @file
 * Contains \Drupal\migrate_drupal\Tests\d7\MigrateDrupal7TestBase.
 */

namespace Drupal\migrate_drupal\Tests\d7;

use Drupal\migrate_drupal\Tests\MigrateDrupalTestBase;

/**
 * Base class for Drupal 7 migration tests.
 */
abstract class MigrateDrupal7TestBase extends MigrateDrupalTestBase {

  public $databaseDumpFiles = [
    __DIR__ . '/../../../tests/fixtures/drupal-7.standard.php',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installMigrations('Drupal 7');
  }

}
