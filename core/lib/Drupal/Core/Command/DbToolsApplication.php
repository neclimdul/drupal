<?php

/**
 * @file
 * Contains \Drupal\Core\Command\DbToolsApplication.
 */

namespace Drupal\Core\Command;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Provides a command to import a database generation script.
 */
class DbToolsApplication extends Application {

  public function __construct() {
    parent::__construct('Database Tools', '8.0.x');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCommands() {
    $default_commands = parent::getDefaultCommands();
    $default_commands[] = new DbImportCommand();
    $default_commands[] = new DbDumpCommand();
    return $default_commands;
  }
}
