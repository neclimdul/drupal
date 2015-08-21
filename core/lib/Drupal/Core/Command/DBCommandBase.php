<?php
/**
 * @file
 *
 */

namespace Drupal\Core\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class DBCommandBase extends Command {

  protected function configure() {
    $this->addOption('database', NULL, InputOption::VALUE_OPTIONAL, 'The database connection name to use.', 'default')
      ->addOption('database-url', 'db-url', InputOption::VALUE_OPTIONAL, 'A database url to parse and use as the database connection.');
  }

}
