<?php

/**
 * @file
 * Contains \Drupal\Core\Command\DbToolsApplication.
 */

namespace Drupal\Core\Command;

use Symfony\Component\Console\Application;

/**
 * Provides a command to import a database generation script.
 */
class DbToolsApplication extends Application {

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct('Database Tools', '8.0.x');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCommands() {
    $default_commands = parent::getDefaultCommands();
    return $default_commands;
  }

}
